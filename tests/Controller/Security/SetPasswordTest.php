<?php

declare(strict_types=1);

namespace App\Tests\Controller\Security;

use App\Entity\SocialAccount;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Behavioural contract for /api/set-password.
 *
 * The endpoint lets an authenticated user set a password on their OWN
 * account, primarily to bridge OAuth-only users to email + password
 * sign-in. The security model is that identity comes from the active
 * session (`#[CurrentUser]`), never from the request body — so the
 * test suite guards:
 *
 *   1. Anonymous request  -> 401 (firewall must reject before the
 *      controller sees it).
 *   2. Authenticated request -> 200 and password is hashed and
 *      persisted. Plaintext must NEVER reach the database.
 *   3. Authenticated request -> hasPassword flips to true on /api/me
 *      after the call (the SPA uses that flag to hide the "Set
 *      password" prompt).
 *   4. Authenticated request -> cannot target another user even by
 *      stuffing an `email` field into the body (the DTO has no such
 *      field; the controller only reads the session user).
 *   5. Validation: empty / too-short password -> 422.
 *   6. Existing password is overwritten by a new one (rotation path).
 *   7. OAuth-only user can add it without losing SocialAccount
 *      linkage — so the same User can sign in via both Google and
 *      email + password afterwards.
 */
final class SetPasswordTest extends WebTestCase
{
    private const PATH = '/api/set-password';

    private const STRONG_PASSWORD = 'SetPasswordTestPass123!';
    private const NEW_PASSWORD = 'AnotherNewPass456!';

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        // The test database persists between phpunit runs and across
        // test files in the same suite. Clean it before each test so
        // we don't collide on the email UNIQUE constraint. We only
        // touch test-owned data (everything in the user table at
        // test time is test data — production never runs against
        // this DB).
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $conn = $em->getConnection();
        $conn->executeStatement(
            'TRUNCATE TABLE social_account, reset_password_request, "user" RESTART IDENTITY CASCADE'
        );
        self::ensureKernelShutdown();
    }

    /**
     * Provisions a fresh User with no password (simulates the
     * OAuth-only state). MUST be called AFTER createClient() because
     * Symfony's WebTestCase only allows the kernel to boot once per
     * test, and we need the container to reach the EntityManager.
     */
    private function provisionOAuthOnlyUser(string $email): User
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_CANDIDATE']);
        $user->setIsVerified(true);
        // password left NULL on purpose
        $em->persist($user);
        $em->flush();
        return $user;
    }

    /**
     * Logs the given user in via Symfony's KernelBrowser::loginUser,
     * which writes the session token directly. We bypass /api/login
     * because the OAuth-only user has no password to verify.
     */
    private function loginAs(KernelBrowser $client, User $user): void
    {
        $client->loginUser($user);
    }

    private function postSetPassword(KernelBrowser $client, array $body): \Symfony\Component\HttpFoundation\Response
    {
        $client->request(
            'POST',
            self::PATH,
            server: [
                'CONTENT_TYPE' => 'application/json',
                // OriginValidationSubscriber rejects authenticated
                // state-changing /api/* requests whose Origin is not
                // in the CORS allowlist. Tests inherit the dev
                // allowlist (http://localhost:5174); mirror it so the
                // subscriber lets the request through. Adding Origin
                // explicitly also documents what production traffic
                // looks like.
                'HTTP_ORIGIN' => 'http://localhost:5174',
            ],
            content: json_encode($body, JSON_THROW_ON_ERROR),
        );
        return $client->getResponse();
    }

    public function testUnauthenticatedRequestReturns401(): void
    {
        $client = self::createClient();
        $response = $this->postSetPassword($client, ['password' => self::STRONG_PASSWORD]);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testEmptyPasswordReturns422(): void
    {
        $client = self::createClient();
        $user = $this->provisionOAuthOnlyUser('set-pw-empty-' . __LINE__ . '@example.test');
        $this->loginAs($client, $user);

        $response = $this->postSetPassword($client, ['password' => '']);
        self::assertSame(422, $response->getStatusCode());
    }

    public function testShortPasswordReturns422(): void
    {
        $client = self::createClient();
        $user = $this->provisionOAuthOnlyUser('set-pw-short-' . __LINE__ . '@example.test');
        $this->loginAs($client, $user);

        $response = $this->postSetPassword($client, ['password' => 'short']);
        self::assertSame(422, $response->getStatusCode());
    }

    public function testOAuthOnlyUserCanSetPasswordAndItIsHashed(): void
    {
        $client = self::createClient();
        $email = 'set-pw-oauth-' . __LINE__ . '@example.test';
        $user = $this->provisionOAuthOnlyUser($email);

        // Sanity: starts OAuth-only.
        self::assertNull($user->getPassword());

        $this->loginAs($client, $user);

        $response = $this->postSetPassword($client, ['password' => self::STRONG_PASSWORD]);
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('success', $body['status'] ?? null);
        self::assertTrue($body['hasPassword'] ?? false);

        // The plaintext must NOT be persisted.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($reloaded, 'User must still exist after set-password.');
        self::assertNotNull($reloaded->getPassword(), 'Password must be set after the call.');
        self::assertNotSame(
            self::STRONG_PASSWORD,
            $reloaded->getPassword(),
            'Plaintext password must never be persisted — Symfony must hash it.',
        );
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue(
            $hasher->isPasswordValid($reloaded, self::STRONG_PASSWORD),
            'Newly stored hash must verify the plaintext.',
        );
    }

    public function testHasPasswordFlagOnMeFlipsToTrueAfterSet(): void
    {
        $client = self::createClient();
        $email = 'set-pw-me-' . __LINE__ . '@example.test';
        $user = $this->provisionOAuthOnlyUser($email);
        $this->loginAs($client, $user);

        // /api/me before — hasPassword=false.
        $client->request('GET', '/api/me');
        $meBefore = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($meBefore['hasPassword'] ?? null);

        $response = $this->postSetPassword($client, ['password' => self::STRONG_PASSWORD]);
        if ($response->getStatusCode() !== 200) {
            fwrite(STDERR, "DEBUG 403 body: " . $response->getContent() . "\n");
        }
        self::assertSame(200, $response->getStatusCode());

        $client->request('GET', '/api/me');
        $meAfter = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($meAfter['hasPassword'] ?? null);
    }

    public function testCannotSetPasswordForAnotherUser(): void
    {
        $client = self::createClient();

        // User A is logged in.
        $aEmail = 'set-pw-a-' . __LINE__ . '@example.test';
        $a = $this->provisionOAuthOnlyUser($aEmail);

        // User B exists but is NOT logged in.
        $bEmail = 'set-pw-b-' . __LINE__ . '@example.test';
        $b = $this->provisionOAuthOnlyUser($bEmail);

        $this->loginAs($client, $a);

        // The attacker tries to set B's password by stuffing fields
        // into the body. Even if they put a plausible email/userId
        // key, the DTO has no such field, so the controller only
        // touches A.
        $response = $this->postSetPassword($client, [
            'password' => self::STRONG_PASSWORD,
            'email' => $bEmail,
            'userId' => $b->getId(),
        ]);
        self::assertSame(200, $response->getStatusCode());

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $aReloaded = $em->getRepository(User::class)->findOneBy(['email' => $aEmail]);
        $bReloaded = $em->getRepository(User::class)->findOneBy(['email' => $bEmail]);

        self::assertNotNull($aReloaded);
        self::assertNotNull($bReloaded);
        self::assertNotNull(
            $aReloaded->getPassword(),
            'A is the session user — their password should be set.',
        );
        self::assertNull(
            $bReloaded->getPassword(),
            'B is NOT the session user — their password must remain NULL.',
        );
    }

    public function testExistingPasswordIsReplacedByNewOne(): void
    {
        $client = self::createClient();
        $email = 'set-pw-rotate-' . __LINE__ . '@example.test';
        $user = $this->provisionOAuthOnlyUser($email);
        $this->loginAs($client, $user);

        // First set: STRONG_PASSWORD
        $this->postSetPassword($client, ['password' => self::STRONG_PASSWORD]);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $firstHash = $em->getRepository(User::class)->findOneBy(['email' => $email])->getPassword();
        self::assertNotNull($firstHash);

        // Second set: NEW_PASSWORD — must replace, not stack.
        $this->postSetPassword($client, ['password' => self::NEW_PASSWORD]);

        $em->clear();
        $reloaded = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotSame(
            $firstHash,
            $reloaded->getPassword(),
            'Password rotation must replace the hash, not keep the old one.',
        );

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($reloaded, self::NEW_PASSWORD));
        self::assertFalse($hasher->isPasswordValid($reloaded, self::STRONG_PASSWORD));
    }

    public function testOAuthOnlyUserWithSocialAccountCanSetPassword(): void
    {
        $client = self::createClient();

        // Full OAuth-only model: password=NULL, SocialAccount(Google).
        $email = 'set-pw-google-' . __LINE__ . '@example.test';
        $user = $this->provisionOAuthOnlyUser($email);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $sa = new SocialAccount();
        $sa->setProvider('google');
        $sa->setProviderUserId('google-id-' . __LINE__);
        $sa->setUser($user);
        $em->persist($sa);
        $em->flush();

        $this->loginAs($client, $user);
        $response = $this->postSetPassword($client, ['password' => self::STRONG_PASSWORD]);

        self::assertSame(200, $response->getStatusCode());

        // Both SocialAccount and password survive the operation.
        $em->clear();
        $reloaded = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($reloaded->getPassword());
        self::assertCount(
            1,
            $em->getRepository(SocialAccount::class)->findBy(['user' => $reloaded]),
            'SocialAccount linkage must be preserved when setting a password.',
        );
    }
}