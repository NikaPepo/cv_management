<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreateProjectDto;
use App\DTO\UpdateProjectDto;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProfileRepository;
use App\Repository\ProjectRepository;
use App\Repository\TechnologyTagRepository;
use App\Service\ProjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/projects')]
final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly ProjectRepository $projectRepository,
        private readonly TechnologyTagRepository $tagRepository,
        private readonly ProfileRepository $profileRepository,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }
        $profile = $this->profileRepository->ensureForUser($user);
        return $this->json(array_map([self::class, 'present'], $this->projectRepository->findForProfile($profile)));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateProjectDto $dto,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        $profile = $this->requireProfile($user);
        $project = $this->projectService->create($profile, $dto);
        return $this->json(self::present($project), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateProjectDto $dto,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        $profile = $this->requireProfile($user);
        $project = $this->projectRepository->find($id);
        if ($project === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->projectService->update($profile, $project, $dto);
        return $this->json(self::present($project));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        $profile = $this->requireProfile($user);
        $project = $this->projectRepository->find($id);
        if ($project === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->projectService->delete($profile, $project);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/tags', methods: ['GET'])]
    public function tags(Request $request): JsonResponse
    {
        $prefix = (string) $request->query->get('prefix', '');
        $limit = max(1, min(50, $request->query->getInt('limit', 10)));
        return $this->json(
            array_map(static fn ($t) => ['id' => $t->getId(), 'name' => $t->getName()], $this->tagRepository->findByPrefix($prefix, $limit))
        );
    }

    /** @return array<string, mixed> */
    public static function present(Project $project): array
    {
        return [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'periodStart' => $project->getPeriodStart()?->format('Y-m-d'),
            'periodEnd' => $project->getPeriodEnd()?->format('Y-m-d'),
            'markdownDescription' => $project->getMarkdownDescription(),
            'technologyTags' => array_map(
                static fn ($t) => ['id' => $t->getId(), 'name' => $t->getName()],
                $project->getTechnologyTags()->toArray()
            ),
            'createdAt' => $project->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function requireProfile(?User $user): \App\Entity\Profile
    {
        if ($user === null) {
            throw $this->createAccessDeniedException();
        }
        return $this->profileRepository->ensureForUser($user);
    }
}