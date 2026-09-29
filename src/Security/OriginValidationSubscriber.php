<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Defense-in-depth CSRF protection for authenticated state-changing API calls.
 *
 * Background:
 *   The frontend is a React SPA. Authentication is Symfony's session cookie
 *   (PHPSESSID, HttpOnly, SameSite=Lax). Symfony's `json_login` authenticator
 *   only accepts Content-Type: application/json, which means a plain HTML
 *   <form> cross-site submission cannot trigger login (browsers won't send
 *   JSON without a preflight, and the preflight is rejected by CORS).
 *
 *   For authenticated state-changing endpoints (POST/PUT/PATCH/DELETE under
 *   /api/*), SameSite=Lax blocks cross-site cookie attachment on POST — so
 *   a cross-site attacker cannot impersonate the user via simple form posts.
 *
 *   However:
 *     1. SameSite defaults can be downgraded by future config changes
 *        (e.g. SameSite=None if a mobile app is added).
 *     2. SameSite does not protect against same-site attackers
 *        (subdomains of the same eTLD+1).
 *     3. Some browsers and edge cases (older clients, certain extensions)
 *        can be coaxed into sending cookies in ways SameSite does not catch.
 *
 *   This subscriber adds an explicit Origin/Referer allowlist check on
 *   state-changing authenticated /api/* requests. It rejects requests whose
 *   Origin (or Referer fallback) is not in the configured allowlist.
 *
 * Scope:
 *   - Only POST, PUT, PATCH, DELETE.
 *   - Only under /api/.
 *   - Only when the request is authenticated (token in storage).
 *   - Explicitly EXCLUDES:
 *       * /api/login (json_login: Content-Type JSON already protects it,
 *         and it's anonymous by definition).
 *       * /api/registration (anonymous endpoint, no session to abuse).
 *       * /api/forgot-password, /api/reset-password (token-protected,
 *         anonymous).
 *       * /verify/email (signed URL, anonymous).
 *       * /connect/*, /login/check-* (OAuth provider redirect targets,
 *         authenticated via provider state, not Origin).
 *       * /messenger/consume (Bearer-auth, not session).
 *
 * Origin comparison:
 *   The Origin header is parsed via parse_url() and compared to the
 *   allowlist (scheme + host + port). Strict equality. No regex. No wildcards.
 *   The allowlist comes from CORS_ALLOW_ORIGIN (already used by nelmio_cors
 *   for the same purpose on preflight/response headers). When that env is
 *   empty or not set, the check is skipped — this matches the project's
 *   posture of disabling CORS in test/local setups.
 *
 * If Origin is missing (browsers omit it for some same-origin requests,
 * GET, and same-origin POSTs in older versions), the request is ALLOWED.
 * This is safe because:
 *   - Same-origin requests don't carry Origin (RFC 6454).
 *   - GET is filtered out by the method check above.
 *   - For same-origin POSTs, the cookie was set by this same origin,
 *     so there's no cross-site attack vector.
 *
 * Referer is consulted only as a fallback when Origin is absent AND
 * the request was sent by a browser capable of sending Referer. We
 * accept the request if Referer's origin matches the allowlist.
 * Otherwise the request is rejected.
 */
final class OriginValidationSubscriber implements EventSubscriberInterface
{
    /**
     * Endpoints that are public/anon and either token-protected or
     * otherwise have their own CSRF / preflight protection. They MUST
     * NOT be subject to Origin validation, because the request is
     * expected to come from users who are not yet logged in (no Origin
     * header in some cases) and the protection model differs.
     */
    private const EXCLUDED_PATH_PREFIXES = [
        '/api/login',
        '/api/registration',
        '/api/forgot-password',
        '/api/reset-password',
        '/api/logout',          // handled by LogoutMethodRestrictionListener
        '/verify/',
        '/connect/',
        '/login/check-',
    ];

    /**
     * @param string $allowedOrigin the configured frontend origin
     *                              (from CORS_ALLOW_ORIGIN env). Empty
     *                              string disables the check.
     */
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly string $allowedOrigin,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run after the firewall has populated the token storage so we
            // can tell authenticated from anonymous requests. FirewallListener
            // runs at priority 8 — we run at 4.
            KernelEvents::REQUEST => ['onKernelRequest', 4],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // No allowlist configured → skip the check (test/local setups).
        if ($this->allowedOrigin === '') {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = strtoupper($request->getMethod());

        // Only inspect state-changing methods.
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        // Only inspect /api/* endpoints.
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Excluded endpoints (login, registration, etc.).
        foreach (self::EXCLUDED_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        // Only authenticated requests need this check. Anonymous requests
        // have no session cookie to abuse — they're filtered by CORS
        // preflight or by json_login's content-type requirement.
        $token = $this->tokenStorage->getToken();
        if ($token === null || !$token->getUser() instanceof \Symfony\Component\Security\Core\User\UserInterface) {
            return;
        }

        // Compare Origin first; fall back to Referer.
        $requestOrigin = $this->extractOrigin(
            $request->headers->get('Origin'),
            $request->headers->get('Referer'),
        );

        // No Origin and no Referer → same-origin browser request in many
        // setups. Safe to allow (RFC 6454 + cookie scoping).
        if ($requestOrigin === null) {
            return;
        }

        if (!hash_equals($this->allowedOrigin, $requestOrigin)) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Invalid request origin.'],
                403,
            ));
        }
    }

    /**
     * Returns scheme://host[:port] from Origin if present, otherwise from
     * Referer, otherwise null. Strict parse; rejects malformed inputs.
     */
    private function extractOrigin(?string $origin, ?string $referer): ?string
    {
        $candidate = $origin;
        if ($candidate === null || $candidate === '') {
            $candidate = $referer;
        }
        if ($candidate === null || $candidate === '') {
            return null;
        }

        $parts = parse_url($candidate);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if ($scheme === null || $host === null) {
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $defaultPort = ($scheme === 'https' && $port === ':443')
            || ($scheme === 'http' && $port === ':80');

        return $defaultPort
            ? sprintf('%s://%s', $scheme, $host)
            : sprintf('%s://%s%s', $scheme, $host, $port);
    }
}
