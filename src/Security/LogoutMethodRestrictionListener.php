<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Restricts the firewall logout endpoint to POST only.
 *
 * Background:
 *   Symfony's FirewallListener (kernel.request, priority 8) dispatches the
 *   LogoutListener for every HTTP method whose path matches `logout_path`
 *   (see Symfony\Security\Http\Firewall\LogoutListener::supports()).
 *   In particular, a cross-site top-level GET navigation to
 *   `https://api.example.com/api/logout` sends the PHPSESSID cookie under
 *   SameSite=Lax and triggers logout, which is a denial-of-service vector
 *   (an attacker logs the victim out by embedding an <img>, an <a>, or a
 *   window.open() on a malicious page).
 *
 * Fix:
 *   This listener runs at priority 9 (strictly above FirewallListener's 8)
 *   and short-circuits the request with HTTP 405 Method Not Allowed for
 *   any non-POST method on the logout path. The firewall listener never
 *   sees the request, so logout never runs.
 *
 *   SameSite=Lax already blocks cross-site POST requests (no cookie sent),
 *   so POST remains safe. GET is the only method that needed closing.
 *
 * No configuration needed: the logout path is read directly from
 * security.yaml's `firewalls.main.logout.path` via the injected string.
 */
final class LogoutMethodRestrictionListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $logoutPath,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Priority 9 runs strictly before FirewallListener (priority 8).
            // Symfony convention: higher priority = earlier execution.
            KernelEvents::REQUEST => ['onKernelRequest', 9],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Only act on the main request, not on sub-requests.
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Fast path: only inspect the logout endpoint.
        if ($path !== $this->logoutPath) {
            return;
        }

        // POST is the only method that should ever trigger logout.
        // SameSite=Lax already protects cross-site POST (no cookie sent),
        // so this restriction closes only the cross-site GET loophole.
        if ($request->getMethod() === 'POST') {
            return;
        }

        $event->setResponse(new Response(
            '',
            Response::HTTP_METHOD_NOT_ALLOWED,
            ['Allow' => 'POST'],
        ));
    }
}
