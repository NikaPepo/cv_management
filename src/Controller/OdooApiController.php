<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PositionApiToken;
use App\Service\OdooAggregationService;
use App\Service\PositionApiTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only API for the Odoo integration. The Position is determined
 * exclusively by the bearer token — there is no path or query
 * parameter for it. Invalid, missing, and revoked tokens all surface
 * as the same 401 with the same body so an attacker cannot probe
 * which tokens or Positions exist.
 */
#[Route('/api/odoo')]
final class OdooApiController extends AbstractController
{
    public function __construct(
        private readonly PositionApiTokenService $tokenService,
        private readonly OdooAggregationService $aggregationService,
    ) {
    }

    #[Route('/positions/aggregates', methods: ['GET'])]
    public function aggregates(): JsonResponse
    {
        $token = $this->resolveToken();
        if ($token === null) {
            return $this->json(['error' => 'invalid_token'], Response::HTTP_UNAUTHORIZED);
        }

        $this->tokenService->markUsed($token);

        return $this->json($this->aggregationService->aggregate($token->getPosition()));
    }

    private function resolveToken(): ?PositionApiToken
    {
        $header = (string) $this->container->get('request_stack')->getCurrentRequest()->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return null;
        }
        return $this->tokenService->findActiveBySecret($m[1]);
    }
}
