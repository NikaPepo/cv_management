<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\UpdateProfileDto;
use App\Entity\User;
use App\Repository\ProfileRepository;
use App\Service\ProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/profile')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly ProfileRepository $profileRepository,
    ) {
    }

    #[Route('/me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }
        $profile = $this->profileRepository->ensureForUser($user);
        return $this->json(self::present($profile));
    }

    /**
     * Auto-save endpoint. The frontend debounces calls (5–10s) and passes
     * the version it last read. A stale version returns 409 Conflict.
     */
    #[Route('/me', methods: ['PATCH'])]
    public function patch(
        #[MapRequestPayload] UpdateProfileDto $dto,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }
        $profile = $this->profileRepository->ensureForUser($user);
        $this->profileService->update($profile, $dto);
        return $this->json(self::present($profile));
    }

    /** @return array<string, mixed> */
    public static function present(\App\Entity\Profile $profile): array
    {
        return [
            'id' => $profile->getId(),
            'firstName' => $profile->getFirstName(),
            'lastName' => $profile->getLastName(),
            'location' => $profile->getLocation(),
            'photoUrl' => $profile->getPhotoUrl(),
            'version' => $profile->getVersion(),
            'updatedAt' => $profile->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}