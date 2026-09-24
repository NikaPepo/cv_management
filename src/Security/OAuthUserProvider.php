<?php

namespace App\Security;

use App\Entity\Profile;
use App\Entity\SocialAccount;
use App\Entity\User;
use App\Repository\SocialAccountRepository;
use App\Repository\UserRepository;
use App\Service\ProfileFactory;
use Doctrine\ORM\EntityManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use LogicException;

readonly class OAuthUserProvider implements
    UserProviderInterface,
    OAuthAwareUserProviderInterface
{
    public function __construct(
        private UserRepository          $userRepository,
        private SocialAccountRepository $socialAccountRepository,
        private EntityManagerInterface  $entityManager,
        private RequestStack            $requestStack,
        private ProfileFactory          $profileFactory
    ) {
    }

    public function loadUserByOAuthUserResponse(
        UserResponseInterface $response
    ): UserInterface {
        $provider = $response->getResourceOwner()->getName();

        $providerUserId = $response->getUserIdentifier();

        $email = $response->getEmail();


        $socialAccount = $this->socialAccountRepository
            ->findOneByProviderAndProviderUserId(
                $provider,
                $providerUserId
            );

        if ($socialAccount !== null) {
            return $socialAccount->getUser();
        }

        $user = $this->userRepository->findOneByEmail($email);

        if ($user !== null) {

            $socialAccount = new SocialAccount();

            $socialAccount->setProvider($provider);
            $socialAccount->setProviderUserId($providerUserId);
            $socialAccount->setUser($user);

            $this->entityManager->persist($socialAccount);
            $this->entityManager->flush();

            return $user;
        }

        $user = new User();

        $user->setEmail($email);


        $user->setIsVerified(true);
        // roles stays at the entity default ([]). The role is assigned later
        // by OAuthRegistrationService once the candidate picks Candidate /
        // Recruiter on the React /choose-account-type page. Until then the
        // OAuthSuccessHandler routes the user to that page based on the
        // `oauth_registration_pending` session flag set just below.

        $profile = $this->profileFactory->createForUser($user);

        $socialAccount = new SocialAccount();

        $socialAccount->setProvider($provider);
        $socialAccount->setProviderUserId($providerUserId);
        $socialAccount->setUser($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($profile);
        $this->entityManager->persist($socialAccount);

        $this->entityManager->flush();


        $this->requestStack
            ->getSession()
            ->set('oauth_registration_pending', true);

        return $user;
    }

    public function loadUserByIdentifier(
        string $identifier
    ): UserInterface {
        $user = $this->userRepository->findOneByEmail($identifier);

        if ($user === null) {
            throw new LogicException(
                'User with this email was not found.'
            );
        }

        return $user;
    }

    public function refreshUser(
        UserInterface $user
    ): UserInterface {
        if (!$user instanceof User) {
            throw new LogicException(
                'Unsupported user class.'
            );
        }

        // Re-fetch from DB so the returned User is managed by the current
        // request's EntityManager. Without this, the in-session instance is
        // detached in the new request, mutations like setRoles() are not
        // tracked by the UnitOfWork, and entityManager->flush() is a no-op.
        $refreshed = $this->userRepository->find($user->getId());
        if ($refreshed === null) {
            throw new LogicException(
                sprintf('User %d no longer exists.', $user->getId())
            );
        }

        return $refreshed;
    }

    public function supportsClass(
        string $class
    ): bool {
        return $class === User::class;
    }
}

