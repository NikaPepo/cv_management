<?php

declare(strict_types=1);

namespace App\Tests\Controller\Security;

use App\Entity\SocialAccount;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Behavioural contract for /api/registration duplicate-email handling.
 *
 * The architectural rule: there is exactly one User per email. When a
 * second registration attempt arrives for an email that is already on
 * file, the server MUST refuse with 409 and MUST NOT create a second
 * row — regardless of whether the existing user is:
 *   - a normal email + password account,
 *   - OAuth-only (password=NULL, SocialAccount linked),
 *   - OAuth-only without a SocialAccount.
 *
 * The error body should help without leaking which path the existing
 * user originally took (we never want to enable user enumeration by
 * saying "you signed up via Google").
 */
final class RegistrationDuplicateTest extends WebTestCase
{
    private const PATH = '/api/registration';

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        // The test database persists between phpunit runs. Clean it
        // before each test so we don't collide on the email UNIQUE
        // constraint. Everything in the user table at test time is
        // test data — production never runs against this DB.
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $conn = $em->getConnection();
        $conn->executeStatement(
            'TRUNCATE TABLE social_account, reset_password_request, "user" RESTART IDENTITY CASCADE'
        );
        self::ensureKernelShutdown();
    }

    private function postRegistration(KernelBrowser $client, array $body): \Symfony\Component\HttpFoundation\Response
    {
        $client->request(
            'POST',
            self::PATH,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($body, JSON_THROW_ON_ERROR),
        );
        return $client->getResponse();
    }

    private function provisionUserWithPassword(string $email): User
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_CANDIDATE']);
        $user->setIsVerified(true);
        $user->setPassword('already-hashed-by-symfony-not-relevant-here');
        $em->persist($user);
        $em->flush();
        return $user;
    }

    private function provisionOAuthOnlyUser(string $email): User
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_CANDIDATE']);
        $user->setIsVerified(true);
        // password left NULL
        $em->persist($user);
        $em->flush();
        return $user;
    }

    public function testDuplicateWithExistingEmailPasswordUserReturns409(): void
    {
        $client = self::createClient();
        $email = 'dup-existing-' . __LINE__ . '@example.test';
        $this->provisionUserWithPassword($email);

        $response = $this->postRegistration($client, [
            'email' => $email,
            'password' => 'NewStrongPass123!',
            'accountType' => 'candidate',
        ]);

        self::assertSame(409, $response->getStatusCode());

        // We never want a second row to be created.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $rows = $em->getRepository(User::class)->findBy(['email' => $email]);
        self::assertCount(
            1,
            $rows,
            'There must be exactly one User row per email even after a duplicate registration attempt.',
        );
    }

    public function testDuplicateWithOAuthOnlyUserReturns409(): void
    {
        $client = self::createClient();
        $email = 'dup-oauth-' . __LINE__ . '@example.test';
        $this->provisionOAuthOnlyUser($email);

        $response = $this->postRegistration($client, [
            'email' => $email,
            'password' => 'NewStrongPass123!',
            'accountType' => 'candidate',
        ]);

        self::assertSame(409, $response->getStatusCode());

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $rows = $em->getRepository(User::class)->findBy(['email' => $email]);
        self::assertCount(1, $rows);

        // The existing OAuth-only user must keep password=NULL — the
        // duplicate-attempt must NOT have promoted them or touched
        // their row.
        self::assertNull(
            $rows[0]->getPassword(),
            'A duplicate registration attempt must not modify the existing OAuth-only user.',
        );
    }

    public function testDuplicateWithOAuthOnlyUserPlusSocialAccountReturns409(): void
    {
        // This is the exact scenario from the bug report: someone who
        // signed up via Google tries to also register an email +
        // password. The response MUST be 409 and MUST NOT create a
        // second User. The existing User retains password=NULL and
        // its SocialAccount.
        $client = self::createClient();
        $email = 'dup-google-' . __LINE__ . '@example.test';
        $user = $this->provisionOAuthOnlyUser($email);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $sa = new SocialAccount();
        $sa->setProvider('google');
        $sa->setProviderUserId('google-id-' . __LINE__);
        $sa->setUser($user);
        $em->persist($sa);
        $em->flush();

        $response = $this->postRegistration($client, [
            'email' => $email,
            'password' => 'NewStrongPass123!',
            'accountType' => 'candidate',
        ]);

        self::assertSame(409, $response->getStatusCode());

        $em->clear();
        $rows = $em->getRepository(User::class)->findBy(['email' => $email]);
        self::assertCount(1, $rows, 'No second User must be created.');

        $reloaded = $rows[0];
        self::assertNull(
            $reloaded->getPassword(),
            'Existing OAuth-only User must keep password=NULL after the duplicate attempt.',
        );
        self::assertCount(
            1,
            $em->getRepository(SocialAccount::class)->findBy(['user' => $reloaded]),
            'Existing SocialAccount linkage must survive the duplicate attempt.',
        );
    }

    public function testDuplicateResponseDoesNotLeakProviderName(): void
    {
        // The error body is user-facing and must NOT differentiate
        // "OAuth-only" from "email/password" — that would let an
        // attacker enumerate which emails are registered and how.
        // The production response is JSON via ConflictHttpException;
        // the dev HTML error page wraps it but the user-facing
        // message itself must not contain a provider name. We assert
        // on the visible error string, not the whole page.
        $client = self::createClient();
        $email = 'dup-leak-' . __LINE__ . '@example.test';
        $this->provisionOAuthOnlyUser($email);

        $response = $this->postRegistration($client, [
            'email' => $email,
            'password' => 'NewStrongPass123!',
            'accountType' => 'candidate',
        ]);

        // Find the visible error string — Symfony's debug exception
        // page embeds it in an HTML comment. We don't care about
        // framework internal markup, only the user-facing copy.
        $body = (string) $response->getContent();
        preg_match('/<!--\s*(.*?(?:User|exists|provider|password|sign in|sign_in|email|Account).*?)\s*-->/si', $body, $m);
        $userFacingMessage = $m[1] ?? '';

        self::assertNotSame('', $userFacingMessage, 'Expected a Symfony debug comment with the user-facing message.');
        self::assertStringNotContainsStringIgnoringCase('google', $userFacingMessage);
        self::assertStringNotContainsStringIgnoringCase('facebook', $userFacingMessage);
        self::assertStringNotContainsStringIgnoringCase('oauth', $userFacingMessage);
    }

    public function testFreshEmailStillRegistersSuccessfully(): void
    {
        $client = self::createClient();
        $email = 'fresh-' . __LINE__ . '@example.test';
        $response = $this->postRegistration($client, [
            'email' => $email,
            'password' => 'NewStrongPass123!',
            'accountType' => 'candidate',
        ]);

        self::assertSame(201, $response->getStatusCode());

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $rows = $em->getRepository(User::class)->findBy(['email' => $email]);
        self::assertCount(1, $rows);
    }
}