<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\MessengerConsumeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Machine-to-machine endpoint invoked by an external scheduler
 * (cron.org) to drain the Symfony Messenger `async` queue.
 *
 * Contract:
 *   - POST /messenger/consume
 *   - Authorization: Bearer <MESSENGER_CRON_TOKEN>
 *   - No user session is required; the route is matched BEFORE the
 *     `main` firewall (see config/packages/security.yaml).
 *   - Returns 200 on success/idle/busy, 401 on missing or wrong token.
 *
 * The controller stays thin: parse + verify the Bearer header, dispatch
 * to the service, translate the service's result code into an HTTP
 * status. All queue logic lives in App\Service\MessengerConsumeService.
 */
final class MessengerConsumeController
{
    /**
     * Bounded by the cron.org timeout and Render's HTTP request budget.
     * 45s leaves comfortable headroom for transport overhead.
     */
    private const CONSUME_TIME_LIMIT_SECONDS = 20;

    public function __construct(
        private readonly MessengerConsumeService $service,
        private readonly string $cronToken,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/messenger/consume',
        name: 'messenger_consume',
        methods: ['POST'],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            // 401 with a stable JSON shape. Body deliberately does not
            // echo "WWW-Authenticate: Bearer realm=..." or anything that
            // could help an attacker differentiate between missing-header
            // and wrong-token failures. The token is never logged.
            return new JsonResponse(
                ['status' => 'unauthorized'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $result = $this->service->consume(self::CONSUME_TIME_LIMIT_SECONDS);

        return match ($result) {
            MessengerConsumeService::RESULT_OK,
            MessengerConsumeService::RESULT_IDLE => new JsonResponse(
                ['status' => 'ok'],
                Response::HTTP_OK,
            ),
            MessengerConsumeService::RESULT_BUSY => new JsonResponse(
                ['status' => 'busy'],
                Response::HTTP_OK, // still a successful cron tick
            ),
            default => new JsonResponse(
                ['status' => 'error'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            ),
        };
    }

    /**
     * Timing-safe Bearer token check. We deliberately do NOT log the
     * Authorization header (Monolog's web processor is configured to
     * strip it, and we never echo it in messages).
     */
    private function isAuthorized(Request $request): bool
    {
        $configured = $this->cronToken;
        if ($configured === '') {
            // Defence in depth: if the env var is missing in production,
            // refuse ALL requests rather than accidentally letting traffic
            // through unauthenticated.
            $this->logger->warning('Messenger cron endpoint hit but MESSENGER_CRON_TOKEN is not configured.');
            return false;
        }

        $header = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return false;
        }
        $presented = substr($header, 7);
        // hash_equals prevents timing-based token leakage.
        return hash_equals($configured, $presented);
    }
}
