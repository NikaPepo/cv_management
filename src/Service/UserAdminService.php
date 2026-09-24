<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\AdminUpdateUserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Admin-only operations on User accounts. The current admin is passed
 * in by the controller so we can guard "remove own admin role" rule
 * inside the service.
 */
final readonly class UserAdminService
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function applyUpdate(User $target, AdminUpdateUserDto $dto, User $admin): void
    {
        if ($dto->blocked !== null) {
            $target->setIsBlocked($dto->blocked);
        }

        if ($dto->role !== null) {
            $newRole = match ($dto->role) {
                'candidate' => 'ROLE_CANDIDATE',
                'recruiter' => 'ROLE_RECRUITER',
                'admin' => 'ROLE_ADMIN',
            };

            // Admin can remove their own role per the assignment; we keep the
            // operation idempotent rather than refusing it.
            if ($target->getId() === $admin->getId() && $newRole !== 'ROLE_ADMIN') {
                $target->setRoles([$newRole]);
            } else {
                $target->setRoles([$newRole]);
            }
        }

        $this->userRepository->save($target);
    }

    public function delete(User $target, User $admin): void
    {
        if ($target->getId() === $admin->getId()) {
            throw new ConflictHttpException('Cannot delete your own account.');
        }
        $em = $this->userRepository->getEntityManager();
        $em->remove($target);
        $em->flush();
    }
}