<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Profile;
use App\Entity\User;

class ProfileFactory
{
    public function createForUser(User $user): Profile
    {
        $profile = new Profile();
        $profile->setUser($user);
        return $profile;
    }
}
