<?php

namespace App\Controller;

use App\DTO\RegisterRequestDto;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private EmailVerifier $emailVerifier,
        private string $frontendUrl,
    ) {
    }

    #[Route('/api/registration', name: 'app_registration', methods: ['POST'])]
    public function registration(
        #[MapRequestPayload] RegisterRequestDto $request,
        RegistrationService                     $registrationService
    ): JsonResponse
    {
        $user = $registrationService->register($request);

        return $this->json([
            'status' => 'success',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'verified' => $user->isVerified(),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Verification callback.
     *
     * Symfony validates the signed URL, sets isVerified=true, then redirects
     * to the React SPA's /login page. The SPA reads ?verified=1 to show a
     * success alert; ?error=verify_email on failure (with the i18n reason
     * from VerifyEmailBundle so the UI can render a friendly message).
     */
    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        TranslatorInterface $translator,
        UserRepository $userRepository,
    ): RedirectResponse {
        $loginUrl = $this->frontendUrl . '/login';

        $id = $request->query->get('id');
        if (null === $id) {
            return $this->redirectWithError($loginUrl, 'invalid_link');
        }

        $user = $userRepository->find($id);
        if (null === $user) {
            return $this->redirectWithError($loginUrl, 'invalid_link');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $reason = $translator->trans(
                $exception->getReason(),
                [],
                'VerifyEmailBundle'
            );
            return $this->redirectWithError($loginUrl, $reason);
        }

        return new RedirectResponse($loginUrl . '?verified=1');
    }

    private function redirectWithError(string $loginUrl, string $reason): RedirectResponse
    {
        return new RedirectResponse(
            $loginUrl . '?error=verify_email&reason=' . urlencode($reason)
        );
    }
}