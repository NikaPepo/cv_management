<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cv;
use App\Entity\User;
use App\Repository\CvLikeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Recruiter-only likes. The DB has UNIQUE(cv_id, recruiter_id); the service
 * also enforces that the actor is a recruiter and that the CV is published.
 */
final readonly class CvLikeService
{
    public function __construct(
        private CvLikeRepository $likeRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function like(Cv $cv, User $recruiter): void
    {
        $this->assertRecruiter($recruiter);
        if ($this->likeRepository->findOneByCvAndRecruiter($cv, $recruiter) !== null) {
            return; // idempotent
        }
        $like = new \App\Entity\CvLike();
        $like->setCv($cv);
        $like->setRecruiter($recruiter);
        $this->entityManager->persist($like);
        $this->entityManager->flush();
    }

    public function unlike(Cv $cv, User $recruiter): void
    {
        $this->assertRecruiter($recruiter);
        $like = $this->likeRepository->findOneByCvAndRecruiter($cv, $recruiter);
        if ($like === null) {
            return;
        }
        $this->entityManager->remove($like);
        $this->entityManager->flush();
    }

    public function hasLiked(Cv $cv, User $recruiter): bool
    {
        return $this->likeRepository->findOneByCvAndRecruiter($cv, $recruiter) !== null;
    }

    private function assertRecruiter(User $user): void
    {
        $roles = $user->getRoles();
        if (!in_array('ROLE_RECRUITER', $roles, true) && !in_array('ROLE_ADMIN', $roles, true)) {
            throw new AccessDeniedHttpException('Only recruiters may like CVs.');
        }
    }
}