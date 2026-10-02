<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Custom failure handler for the json_login firewall.
 *
 * Symfony's default JsonLoginAuthenticator returns
 *   {"error": "<exception messageKey>"}
 * for every AuthenticationException, which is fine for "wrong password"
 * or "unknown email" (both produce "Invalid credentials.") but is not
 * fine for `EmailNotVerifiedException` — the frontend needs to know that
 * the rejection is "please verify your email" so it can show the right
 * copy instead of the generic "Invalid credentials." banner.
 *
 * We map only `EmailNotVerifiedException` to a distinct body; every
 * other AuthenticationException keeps Symfony's default shape so the
 * existing anti-enumeration invariant
 *   wrong-password response === unknown-email response
 * is preserved (the existing LoginTest guards this with explicit string
 * assertions).
 *
 * The HTTP status stays 401 in every case — the user did not
 * authenticate, regardless of the reason.
 */
final class JsonLoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof EmailNotVerifiedException) {
            return new JsonResponse(
                [
                    'error' => 'Email address is not verified.',
                    'code'  => 'email_not_verified',
                ],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        // Default Symfony shape, kept verbatim from JsonLoginAuthenticator
        // so any future change to the upstream default body does not
        // silently drift.
        return new JsonResponse(
            ['error' => strtr($exception->getMessageKey(), $exception->getMessageData())],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}