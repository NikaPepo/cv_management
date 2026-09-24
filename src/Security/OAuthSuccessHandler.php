<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Picks where to send the user after a successful OAuth login.
 *
 *   - New OAuth user (no role yet)  →  /choose-account-type
 *   - Existing OAuth user           →  /
 *
 * The `oauth_registration_pending` flag stays in the session so the React
 * /choose-account-type page can POST it back to the API and have the role
 * assigned. The flag is cleared by OAuthRegistrationController AFTER the
 * role is successfully assigned, not here.
 *
 * Uses the FRONTEND_URL container parameter so the host can be changed
 * per-environment without touching this class.
 */
final readonly class OAuthSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private string $frontendUrl,
    ) {
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token
    ): ?Response {
        $session = $request->getSession();

        if ($session->get('oauth_registration_pending', false)) {
            // callback URL — it pollutes the React app URL otherwise.
            $target = $this->frontendUrl . '/choose-account-type';
            return new RedirectResponse(preg_replace('/#_=_$/', '', $target));
        }

        return new RedirectResponse($this->frontendUrl . '/');
    }
}
