<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\UpdateProfileDto;
use App\Entity\Profile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Auto-save workflow: caller passes the version they read; we refuse
 * with 409 if the row has moved since.
 */
final class ProfileService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function update(Profile $profile, UpdateProfileDto $dto): Profile
    {
        if ($dto->version !== null && $profile->getVersion() !== $dto->version) {
            throw new ConflictHttpException(
                'Profile was modified by another session. Please reload.'
            );
        }

        if ($dto->firstName !== null) {
            $profile->setFirstName($dto->firstName);
        }
        if ($dto->lastName !== null) {
            $profile->setLastName($dto->lastName);
        }
        if ($dto->location !== null) {
            $profile->setLocation($dto->location);
        }
        if ($dto->photoUrl !== null) {
            $profile->setPhotoUrl($dto->photoUrl);
        }

        $this->entityManager->flush();

        return $profile;
    }
}