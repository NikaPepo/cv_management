<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

/**
 * Thrown by AppUserChecker when an email/password user attempts to
 * authenticate before confirming the address they registered with.
 *
 * Why a dedicated exception (and not reusing DisabledException):
 *   - `isBlocked` is an administrative action (admin disabled an account).
 *   - `!isVerified` is a self-service state ("please confirm your email").
 *   - The two need different copy in the API response so the React SPA
 *     can show the right remediation ("verify your email" vs "contact
 *     support / your account is blocked"), but they share the same HTTP
 *     status (401) and the same Symfony-side AccountStatusException
 *     superclass so the firewall handles them uniformly.
 *
 * Why CustomUserMessageAccountStatusException (and not AccountStatusException):
 *   Symfony's AuthenticatorManager::handleAuthenticationFailure wraps every
 *   AccountStatusException in a generic BadCredentialsException to prevent
 *   user-enumeration attacks against the response body. That wrap would
 *   discard our messageKey and silently route us through the default
 *   "Invalid credentials." body — defeating the SPA's ability to show
 *   the right remediation. CustomUserMessageAccountStatusException is the
 *   Symfony-documented escape hatch: subclasses are exempt from the wrap
 *   and their getMessageKey() is shown to the caller verbatim. Combined
 *   with the JsonLoginFailureHandler that detects this exception type,
 *   the SPA can render the dedicated "email_not_verified" copy.
 */
final class EmailNotVerifiedException extends CustomUserMessageAccountStatusException
{
    public function __construct(string $message = 'Email address is not verified.')
    {
        parent::__construct($message);
    }
}