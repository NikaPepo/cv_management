<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class OAuthRegistrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    )
    {
    }

    public function completeRegistration(User $user, string $accountType): void
    {
        $role = match ($accountType) {
            'candidate' => 'ROLE_CANDIDATE',
            'recruiter' => 'ROLE_RECRUITER',
        };
        $user->setRoles([$role]);
        $this->entityManager->flush();
    }
}
