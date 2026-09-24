<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Enforces the "blocked users cannot authenticate" requirement.
 *
 * Symfony's default UserChecker only checks account expiry and credentials
 * consistency, not application-specific flags like User::$isBlocked.
 * We reject blocked accounts BEFORE authentication completes so:
 *   - json_login returns 401 (not 200 followed by 403)
 *   - session token is never written for a blocked user
 */
final class AppUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && $user->isBlocked()) {
            throw new DisabledException('Account is blocked.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Reserved for future post-auth checks (e.g. re-verify isVerified,
        // disable accounts mid-session).
    }
}