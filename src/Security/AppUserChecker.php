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
        if ($user instanceof User) {
            // Blocked is checked first: a blocked account must never be
            // allowed to authenticate, whether it verified its email or
            // not. The order matters — a verified+blocked account is
            // still blocked, and the React UI shows a different copy for
            // each state.
            if ($user->isBlocked()) {
                throw new DisabledException('Account is blocked.');
            }

            // Email/password registration marks the row `isVerified=false`
            // and sends a confirmation link. OAuth-created users have
            // `isVerified=true` set in OAuthUserProvider, so they are not
            // affected by this branch. The check happens pre-password so an
            // attacker probing the form cannot use the response shape to
            // tell whether the password was right — the firewall rejects
            // before the password is even compared.
            if (!$user->isVerified()) {
                throw new EmailNotVerifiedException();
            }
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Reserved for future post-auth checks (e.g. re-verify isVerified,
        // disable accounts mid-session).
    }
}