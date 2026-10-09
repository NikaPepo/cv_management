<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\GeneratePositionApiTokenDto;
use App\Entity\Position;
use App\Entity\PositionApiToken;
use App\Repository\PositionRepository;
use App\Service\PositionApiTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recruiter-facing CRUD for Position-bound API tokens. Mirrors the
 * role gate that PositionController::create/update use.
 */
#[Route('/api/positions/{id}/api-token', requirements: ['id' => '\d+'])]
final class PositionApiTokenController extends AbstractController
{
    public function __construct(
        private readonly PositionApiTokenService $service,
        private readonly PositionRepository $positionRepository,
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function generate(int $id, #[MapRequestPayload] ?GeneratePositionApiTokenDto $dto = null): JsonResponse
    {
        if (!$this->isGranted('ROLE_RECRUITER') && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Recruiter role required.'], Response::HTTP_FORBIDDEN);
        }
        $position = $this->positionRepository->find($id);
        if ($position === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        ['token' => $token, 'secret' => $secret] = $this->service->generate($position, $dto?->label);

        return $this->json(self::presentWithSecret($token, $secret), Response::HTTP_CREATED);
    }

    #[Route('', methods: ['GET'])]
    public function list(int $id): JsonResponse
    {
        if (!$this->isGranted('ROLE_RECRUITER') && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Recruiter role required.'], Response::HTTP_FORBIDDEN);
        }
        $position = $this->positionRepository->find($id);
        if ($position === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'items' => array_map(
                static fn (PositionApiToken $t) => self::present($t),
                $this->service->listForPosition($position)
            ),
        ]);
    }

    #[Route('/{tokenId}', methods: ['DELETE'], requirements: ['tokenId' => '\d+'])]
    public function revoke(int $id, int $tokenId): JsonResponse
    {
        if (!$this->isGranted('ROLE_RECRUITER') && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Recruiter role required.'], Response::HTTP_FORBIDDEN);
        }
        $position = $this->positionRepository->find($id);
        if ($position === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $token = $this->findTokenOwnedByPosition($position, $tokenId);
        if ($token === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->service->revoke($token);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Permanent delete of an already-revoked token. Active tokens are
     * refused with 422 — callers must revoke first. A separate route
     * (rather than e.g. `?hard=1` on the existing DELETE) so the
     * intent is explicit and the action is never reached by accident.
     */
    #[Route(
        '/{tokenId}/hard',
        methods: ['DELETE'],
        requirements: ['tokenId' => '\d+'],
    )]
    public function deleteHard(int $id, int $tokenId): JsonResponse
    {
        if (!$this->isGranted('ROLE_RECRUITER') && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Recruiter role required.'], Response::HTTP_FORBIDDEN);
        }
        $position = $this->positionRepository->find($id);
        if ($position === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $token = $this->findTokenOwnedByPosition($position, $tokenId);
        if ($token === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->service->deleteRevoked($token);
        } catch (\DomainException $e) {
            // Active token — caller must revoke first.
            return $this->json(
                ['error' => 'Token must be revoked before it can be deleted.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function findTokenOwnedByPosition(Position $position, int $tokenId): ?PositionApiToken
    {
        foreach ($this->service->listForPosition($position) as $token) {
            if ($token->getId() === $tokenId) {
                return $token;
            }
        }
        return null;
    }

    /** @return array<string, mixed> */
    public static function present(PositionApiToken $token): array
    {
        return [
            'id' => $token->getId(),
            'positionId' => $token->getPosition()->getId(),
            'label' => $token->getLabel(),
            'prefix' => $token->getPrefix(),
            'createdAt' => $token->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'lastUsedAt' => $token->getLastUsedAt()?->format(\DateTimeInterface::ATOM),
            'revokedAt' => $token->getRevokedAt()?->format(\DateTimeInterface::ATOM),
            'isActive' => !$token->isRevoked(),
        ];
    }

    /** @return array<string, mixed> */
    private static function presentWithSecret(PositionApiToken $token, string $secret): array
    {
        return self::present($token) + ['secret' => $secret];
    }
}
