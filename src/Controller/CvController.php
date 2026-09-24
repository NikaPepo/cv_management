<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreateCvDto;
use App\DTO\SetProfileAttributeDto;
use App\Entity\Cv;
use App\Entity\User;
use App\Repository\CvRepository;
use App\Repository\ProfileRepository;
use App\Service\CvLikeService;
use App\Service\CvService;
use App\Service\CvViewAssembler;
use App\Service\ProfileAttributeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/cvs')]
final class CvController extends AbstractController
{
    public function __construct(
        private readonly CvService $cvService,
        private readonly CvRepository $cvRepository,
        private readonly CvViewAssembler $cvViewAssembler,
        private readonly CvLikeService $likeService,
        private readonly ProfileAttributeService $profileAttributeService,
        private readonly ProfileRepository $profileRepository,
    ) {
    }

    /**
     * The candidate's CVs. Each row is the metadata + access flag the UI
     * needs to render "hidden because access was lost" states without a
     * second round-trip.
     */
    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }
        $profile = $this->profileRepository->ensureForUser($user);

        $rows = [];
        foreach ($this->cvRepository->findForProfile($profile) as $cv) {
            $accessible = $this->cvService->isAccessible($cv, $profile);
            $rows[] = [
                'id' => $cv->getId(),
                'positionId' => $cv->getPosition()->getId(),
                'positionTitle' => $cv->getPosition()->getTitle(),
                'status' => $cv->getStatus()->value,
                'publishedAt' => $cv->getPublishedAt()?->format('Y-m-d'),
                'updatedAt' => $cv->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                'accessible' => $accessible,
                'likeCount' => $cv->likeCount(),
            ];
        }
        return $this->json($rows);
    }

    /**
     * Builds a new CV (DRAFT) for a position the candidate satisfies.
     */
    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateCvDto $dto,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }
        $profile = $this->profileRepository->ensureForUser($user);
        $cv = $this->cvService->createForPosition($profile, $dto->positionId);
        return $this->json(self::summary($cv, accessible: true), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        $cv = $this->cvRepository->find($id);
        if ($cv === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $profile = $user !== null ? $this->profileRepository->ensureForUser($user) : null;
        $isOwner = $profile !== null && $cv->getProfile()->getId() === $profile->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        // Recruiters can only see published CVs (and only after passing access).
        $isStaff = $this->isGranted('ROLE_RECRUITER') || $isAdmin;
        if ($isStaff && !$isOwner && !$isAdmin) {
            if ($cv->getStatus()->value !== 'PUBLISHED') {
                return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
            }
        }

        $accessible = $this->cvService->isAccessible($cv, $profile);

        // The candidate's own CV is always reachable for them (so they can
        // still see "access lost" explanations and not lose their work).
        if (!$isOwner && !$isAdmin && !$accessible) {
            return $this->json(['error' => 'Access lost.'], Response::HTTP_FORBIDDEN);
        }

        $view = $this->cvViewAssembler->assemble($cv);
        $view['hasLiked'] = $isStaff ? $this->likeService->hasLiked($cv, $user) : false;

        return $this->json($view);
    }

    #[Route('/{id}/publish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function publish(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }
        $cv = $this->cvRepository->find($id);
        if ($cv === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $profile = $this->profileRepository->ensureForUser($user);
        $cv = $this->cvService->publish($cv, $profile);
        return $this->json(self::summary($cv, accessible: true));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }
        $cv = $this->cvRepository->find($id);
        if ($cv === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $profile = $this->profileRepository->ensureForUser($user);
        $this->cvService->delete($cv, $profile, $this->isGranted('ROLE_ADMIN'));
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Edit-in-place: the CV's "edit attribute" action is just a
     * ProfileAttributeService::setValue call. There is no separate
     * CV-attribute storage.
     */
    #[Route('/{id}/attributes', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function setAttribute(
        int $id,
        #[MapRequestPayload] SetProfileAttributeDto $dto,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }
        $cv = $this->cvRepository->find($id);
        if ($cv === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $profile = $this->profileRepository->ensureForUser($user);
        if ($cv->getProfile()->getId() !== $profile->getId()) {
            return $this->json(['error' => 'Not your CV.'], Response::HTTP_FORBIDDEN);
        }
        $row = $this->profileAttributeService->setValue($profile, $dto);
        return $this->json(\App\Controller\ProfileAttributeController::present($row));
    }

    /**
     * Recruiter likes / unlikes. Both endpoints are idempotent.
     */
    #[Route('/{id}/like', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function like(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }
        $cv = $this->cvRepository->find($id);
        if ($cv === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->likeService->like($cv, $user);
        return $this->json(['likeCount' => $cv->likeCount(), 'liked' => true]);
    }

    #[Route('/{id}/like', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function unlike(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }
        $cv = $this->cvRepository->find($id);
        if ($cv === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->likeService->unlike($cv, $user);
        return $this->json(['likeCount' => $cv->likeCount(), 'liked' => false]);
    }

    /** @return array<string, mixed> */
    public static function summary(Cv $cv, bool $accessible): array
    {
        return [
            'id' => $cv->getId(),
            'positionId' => $cv->getPosition()->getId(),
            'positionTitle' => $cv->getPosition()->getTitle(),
            'status' => $cv->getStatus()->value,
            'publishedAt' => $cv->getPublishedAt()?->format('Y-m-d'),
            'updatedAt' => $cv->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'accessible' => $accessible,
            'likeCount' => $cv->likeCount(),
        ];
    }
}