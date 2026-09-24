<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CompleteOAuthRegistrationRequestDto;
use App\Entity\User;
use App\Service\OAuthRegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class OAuthRegistrationController extends AbstractController
{
    #[Route(
        '/api/oauth/complete-registration',
        name: 'api_oauth_complete_registration',
        methods: ['POST']
    )]
    public function completeRegistration(
        #[CurrentUser] ?User                                     $user,
        Request                                                  $request,
        #[MapRequestPayload] CompleteOAuthRegistrationRequestDto $data,
        OAuthRegistrationService                                 $oauthRegistrationService,
    ): JsonResponse {
        if ($user === null) {
            return $this->json([
                'error' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$request->getSession()->get('oauth_registration_pending', false)) {
            return $this->json([
                'error' => 'OAuth registration is not pending.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $oauthRegistrationService->completeRegistration(
            $user,
            $data->accountType
        );

        $request->getSession()->remove('oauth_registration_pending');

        return $this->json([
            'status' => 'success',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }
}
