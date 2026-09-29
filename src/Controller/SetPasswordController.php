<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\SetPasswordRequestDto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Lets an authenticated user set / replace the password on their OWN
 * account without going through the email-reset flow.
 *
 * Why this exists:
 *   Users created via Google / Facebook OAuth have `User.password = NULL`
 *   by design. The existing `forgot-password` / `reset-password` flow
 *   already lets those users set a password, but it requires them to
 *   log out, request a reset link, click it from email, and log back in.
 *   That is friction. With this endpoint an OAuth-only user can add a
 *   password while they are still authenticated (e.g. from Profile /
 *   Account settings), and after the call the same User can sign in via
 *   email + password without losing their existing OAuth linkage.
 *
 * Security model:
 *   - Identity is `#[CurrentUser]` — Symfony Security injects the user
 *     from the active session. The request body CANNOT name a different
 *     user; the DTO has no `email` / `userId` field.
 *   - If the request reaches the controller without a session token,
 *     Symfony returns 401 before this method runs. We do an explicit
 *     null check as defence in depth and to keep the type signature
 *     honest for static analysis.
 *   - Password is hashed via `UserPasswordHasherInterface`. Plaintext
 *     is never persisted and never logged.
 *   - This endpoint REPLACES the existing hash if one is already set.
 *     It is therefore also the way a password-only user rotates their
 *     password from inside the SPA without an email round-trip.
 *
 * Not for first-time OAuth-only setup of `isVerified`. Verified status
 * is a separate concern managed by the email-verification flow and by
 * the OAuth user provider's verified-email trust model.
 */
#[Route('/api/set-password', name: 'api_set_password', methods: ['POST'])]
final class SetPasswordController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface      $entityManager,
    ) {
    }

    public function __invoke(
        #[CurrentUser] ?User             $user,
        #[MapRequestPayload] SetPasswordRequestDto $request,
    ): JsonResponse {
        if ($user === null) {
            // Belt-and-braces: Symfony's firewall should have already
            // returned 401, but make the contract explicit.
            return $this->json(
                ['error' => 'Authentication required.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $request->password)
        );
        $this->entityManager->flush();

        return $this->json([
            'status' => 'success',
            'hasPassword' => true,
        ]);
    }
}