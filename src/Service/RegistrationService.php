<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\RegisterRequestDto;
use App\Entity\User;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

readonly class RegistrationService
{
    public function __construct(
        private UserRepository              $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailVerifier $emailVerifier,
        private ProfileFactory $profileFactory,
        private ProfileRepository $profileRepository,
    ){
    }
    public function register(RegisterRequestDto $request): User
    {
        $existingUser = $this->userRepository->findOneBy(
            [
                'email' => $request->email,
            ]
        );
        if (null !== $existingUser) {
            // The User row is unique by email — never create a second
            // row with the same email under any circumstances. This
            // covers both:
            //   1. The user already has an email/password account.
            //   2. The user is OAuth-only (password=NULL, has a
            //      SocialAccount) and is trying to also "register" a
            //      password.
            // In case (2) the right action for them is to use the
            // authenticated /api/set-password endpoint OR the public
            // forgot-password flow — both target the same User row and
            // do not create a duplicate. We surface a single generic
            // message that nudges them there without leaking which
            // path they originally used (we never say "registered via
            // Google", which would enable user enumeration).
            throw new ConflictHttpException(
                'An account with this email already exists. '
                . 'Sign in with the provider you used originally, or use '
                . '"Forgot password" to set a password if you registered '
                . 'by email.'
            );
        }
        $user = new User();
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $request->password
        );
        $user->setPassword($hashedPassword);
        $user->setEmail($request->email);
        $roles = match ($request->accountType){
            'recruiter' => ['ROLE_RECRUITER'],
            'candidate' => ['ROLE_CANDIDATE'],
        };
        $user->setRoles($roles);

        $this->userRepository->save($user);

        $profile = $this->profileFactory->createForUser($user);

        $this->profileRepository->save($profile);

        $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user);

        return $user;
    }
}
