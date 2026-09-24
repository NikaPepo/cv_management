<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreatePositionDto;
use App\DTO\UpdatePositionDto;
use App\Entity\Position;
use App\Entity\User;
use App\Enum\PositionLevel;
use App\Repository\PositionRepository;
use App\Service\PositionAccessEvaluator;
use App\Service\PositionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/positions')]
final class PositionController extends AbstractController
{
    public function __construct(
        private readonly PositionService $positionService,
        private readonly PositionRepository $positionRepository,
        private readonly PositionAccessEvaluator $accessEvaluator,
    ) {
    }

    /**
     * Lists positions accessible to the current user. Anonymous users see
     * public ones (per the assignment), authenticated candidates see
     * public + ones they qualify for.
     */
    #[Route('', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $showAll = $request->query->getBoolean('all', false);
        $company = $request->query->get('company');
        $levelParam = $request->query->get('level');
        $level = $levelParam !== null && $levelParam !== '' ? PositionLevel::tryFrom($levelParam) : null;

        // Recruiters/Admins can request `all=1` to see every position.
        if ($showAll && ($this->isGranted('ROLE_RECRUITER') || $this->isGranted('ROLE_ADMIN'))) {
            return $this->json(array_map(
                static fn (Position $p) => self::present($p, withRules: true),
                $this->positionRepository->findFiltered(is_string($company) ? $company : null, $level)
            ));
        }

        $candidates = $this->positionRepository->findAccessibleCandidates();
        $profile = $user?->getProfile();
        $accessible = array_values(array_filter(
            $candidates,
            fn (Position $p) => $this->accessEvaluator->isAccessible($p, $profile)
        ));

        return $this->json(array_map(
            static fn (Position $p) => self::present($p, withRules: false),
            $accessible
        ));
    }

    #[Route('/latest', methods: ['GET'])]
    public function latest(): JsonResponse
    {
        return $this->json(array_map(
            static fn (Position $p) => self::present($p),
            $this->positionRepository->findLatest(10)
        ));
    }

    #[Route('/popular', methods: ['GET'])]
    public function popular(): JsonResponse
    {
        $rows = $this->positionRepository->findTopPopular(5);
        return $this->json(array_map(static function (array $row) {
            $position = $row[0];
            return self::present($position) + ['submittedCvs' => (int) $row['cvs']];
        }, $rows));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        $position = $this->positionRepository->find($id);
        if ($position === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $profile = $user?->getProfile();
        $accessible = $this->accessEvaluator->isAccessible($position, $profile);

        return $this->json(self::present($position, withRules: true) + ['accessible' => $accessible]);
    }

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreatePositionDto $dto): JsonResponse
    {
        if (!$this->isGranted('ROLE_RECRUITER')) {
            return $this->json(['error' => 'Recruiter role required.'], Response::HTTP_FORBIDDEN);
        }
        $position = $this->positionService->create($dto);
        return $this->json(self::present($position, withRules: true), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, #[MapRequestPayload] UpdatePositionDto $dto): JsonResponse
    {
        if (!$this->isGranted('ROLE_RECRUITER')) {
            return $this->json(['error' => 'Recruiter role required.'], Response::HTTP_FORBIDDEN);
        }
        $position = $this->positionRepository->find($id);
        if ($position === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->positionService->update($position, $dto);
        return $this->json(self::present($position, withRules: true));
    }

    #[Route('/{id}/duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(int $id, Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_RECRUITER')) {
            return $this->json(['error' => 'Recruiter role required.'], Response::HTTP_FORBIDDEN);
        }
        $position = $this->positionRepository->find($id);
        if ($position === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $payload = json_decode($request->getContent() ?: '{}', true);
        $title = is_array($payload) && isset($payload['title']) && is_string($payload['title']) ? $payload['title'] : null;
        $copy = $this->positionService->duplicate($position, $title);
        return $this->json(self::present($copy, withRules: true), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        if (!$this->isGranted('ROLE_RECRUITER')) {
            return $this->json(['error' => 'Recruiter role required.'], Response::HTTP_FORBIDDEN);
        }
        $position = $this->positionRepository->find($id);
        if ($position === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->positionService->delete($position);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed> */
    public static function present(Position $position, bool $withRules = false): array
    {
        $data = [
            'id' => $position->getId(),
            'version' => $position->getVersion(),
            'title' => $position->getTitle(),
            'shortDescription' => $position->getShortDescription(),
            'company' => $position->getCompany(),
            'level' => $position->getLevel()?->value,
            'isPublic' => $position->isPublic(),
            'maxProjects' => $position->getMaxProjects(),
            'projectTagFilter' => $position->getProjectTagFilter(),
            'attributes' => array_map(static function ($pa) {
                $def = $pa->getAttributeDefinition();
                return [
                    'attributeDefinitionId' => $def->getId(),
                    'name' => $def->getName(),
                    'dataType' => $def->getDataType()->value,
                    'sortOrder' => $pa->getSortOrder(),
                ];
            }, $position->getAttributes()->toArray()),
            'updatedAt' => $position->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($withRules) {
            $data['accessRules'] = array_map(static function ($rule) {
                return [
                    'attributeDefinitionId' => $rule->getAttributeDefinition()->getId(),
                    'attributeName' => $rule->getAttributeDefinition()->getName(),
                    'operator' => $rule->getOperator()->value,
                    'value' => $rule->getValue(),
                ];
            }, $position->getAccessRules()->toArray());
        }

        return $data;
    }
}