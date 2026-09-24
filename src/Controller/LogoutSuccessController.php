<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Symfony's firewall logout always issues a redirect after destroying the
 * session. Without this target route the redirect would 404 (no GET / in
 * our API). Returning 204 keeps the SPA in charge of navigation.
 */
final class LogoutSuccessController extends AbstractController
{
    #[Route('/api/logout-success', name: 'api_logout_success', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}