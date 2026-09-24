<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ForgotPasswordRequestDto;
use App\DTO\ResetPasswordRequestDto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/api')]
final class ResetPasswordController extends AbstractController
{
    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private string $mailerFromAddress,
        private string $mailerFromName,
        private string $frontendUrl,
    ) {
    }

    /**
     * Always returns the same generic message so the endpoint does not
     * leak whether an account exists for the given email.
     */
    #[Route('/forgot-password', name: 'api_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        #[MapRequestPayload] ForgotPasswordRequestDto $request,
    ): JsonResponse {
        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $request->email]);

        // Always return success-shaped response regardless of whether the
        // user exists; we still send a real email when it does.
        $genericOk = ['message' => 'If the account exists, a password reset link has been sent.'];

        if ($user === null) {
            return $this->json($genericOk);
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface) {
            return $this->json($genericOk);
        }

        $resetUrl = $this->frontendUrl . '/reset-password?token=' . $resetToken->getToken();

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
            ->to((string) $user->getEmail())
            ->subject('Password reset')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetUrl' => $resetUrl,
                'resetToken' => $resetToken,
            ]);

        // The text body is the fallback for clients that don't render HTML.
        $email->text(
            "Please reset your password by clicking this link:\n\n"
            . $resetUrl
            . "\n\nThis link will expire in 1 hour."
        );

        $this->mailer->send($email);

        return $this->json($genericOk);
    }

    #[Route('/reset-password', name: 'api_reset_password', methods: ['POST'])]
    public function resetPassword(
        #[MapRequestPayload] ResetPasswordRequestDto $request,
        UserPasswordHasherInterface $passwordHasher,
    ): JsonResponse {
        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper
                ->validateTokenAndFetchUser($request->token);
        } catch (ResetPasswordExceptionInterface) {
            return $this->json(
                ['error' => 'Invalid or expired reset token.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user->setPassword(
            $passwordHasher->hashPassword($user, $request->password)
        );

        $this->resetPasswordHelper->removeResetRequest($request->token);
        $this->entityManager->flush();

        return $this->json(['message' => 'Password has been reset successfully.']);
    }
}