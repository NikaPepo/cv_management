<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(
        #[CurrentUser] ?User $user
    ): JsonResponse
    {
        if (null === $user) {
            // Returning 401 lets axios throw, which authApi.me() catches and
            // maps to `null`. The previous 200 + {error: ...} shape slipped
            // a non-CurrentUser object into AuthContext and made hasRole()
            // throw on `undefined.includes(...)`, blanking the UI.
            return $this->json(null, Response::HTTP_UNAUTHORIZED);
        }
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'isVerified' => $user->isVerified(),
            'roles' => $user->getRoles(),
        ]);
    }
}