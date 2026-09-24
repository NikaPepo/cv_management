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
            throw new ConflictHttpException('User already exists.');
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
