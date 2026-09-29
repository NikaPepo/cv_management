<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\OriginValidationSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Unit tests for the Origin/Referer allowlist on authenticated
 * state-changing /api/* requests.
 *
 * We don't boot the kernel — the subscriber's logic is pure decision
 * logic over (Request, Token, allowedOrigin). Faster than WebTestCase
 * and free of DB / firewall setup.
 */
final class OriginValidationSubscriberTest extends TestCase
{
    private const ALLOWED = 'http://localhost:5174';

    private function buildSubscriber(?TokenInterface $token, string $allowed = self::ALLOWED): OriginValidationSubscriber
    {
        // createStub auto-stubs every method → PHPUnit 13 doesn't complain
        // about missing expectations.
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        return new OriginValidationSubscriber($tokenStorage, $allowed);
    }

    private function buildEvent(string $method, string $path, array $headers): RequestEvent
    {
        $request = Request::create($path, $method);
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function anonymousToken(): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        return $token;
    }

    private function authenticatedToken(): TokenInterface
    {
        $user = $this->createStub(UserInterface::class);
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }

    public function testAllowsAuthenticatedRequestFromAllowedOrigin(): void
    {
        $subscriber = $this->buildSubscriber($this->authenticatedToken());
        $event = $this->buildEvent('POST', '/api/cvs', [
            'Origin' => self::ALLOWED,
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse(), 'Allowed origin must not produce a response.');
    }

    public function testRejectsAuthenticatedRequestFromDisallowedOrigin(): void
    {
        $subscriber = $this->buildSubscriber($this->authenticatedToken());
        $event = $this->buildEvent('POST', '/api/cvs', [
            'Origin' => 'http://evil.example.com',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNotNull($event->getResponse(), 'Disallowed origin must produce a response.');
        self::assertSame(403, $event->getResponse()->getStatusCode());
    }

    public function testRejectsAuthenticatedRequestWhenOriginDiffersByScheme(): void
    {
        $subscriber = $this->buildSubscriber($this->authenticatedToken());
        // HTTPS variant of an allowed HTTP origin must not be treated equal.
        $event = $this->buildEvent('PATCH', '/api/projects/1', [
            'Origin' => 'https://localhost:5174',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertSame(403, $event->getResponse()?->getStatusCode());
    }

    public function testRejectsAuthenticatedRequestWhenOriginDiffersByPort(): void
    {
        $subscriber = $this->buildSubscriber($this->authenticatedToken());
        $event = $this->buildEvent('DELETE', '/api/projects/1', [
            'Origin' => 'http://localhost:5175',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertSame(403, $event->getResponse()?->getStatusCode());
    }

    public function testFallsBackToRefererWhenOriginMissing(): void
    {
        $subscriber = $this->buildSubscriber($this->authenticatedToken());
        $event = $this->buildEvent('POST', '/api/cvs', [
            'Referer' => self::ALLOWED . '/positions',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse(), 'Referer should be accepted when Origin is absent.');
    }

    public function testRejectsWhenRefererFromDifferentOrigin(): void
    {
        $subscriber = $this->buildSubscriber($this->authenticatedToken());
        $event = $this->buildEvent('POST', '/api/cvs', [
            'Referer' => 'http://evil.example.com/whatever',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertSame(403, $event->getResponse()?->getStatusCode());
    }

    public function testAllowsWhenNoOriginAndNoReferer(): void
    {
        $subscriber = $this->buildSubscriber($this->authenticatedToken());
        $event = $this->buildEvent('POST', '/api/cvs', []);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse(), 'Same-origin requests may omit Origin/Referer.');
    }

    public function testAnonymousRequestSkipsCheck(): void
    {
        $subscriber = $this->buildSubscriber($this->anonymousToken());
        $event = $this->buildEvent('POST', '/api/cvs', [
            'Origin' => 'http://evil.example.com',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull(
            $event->getResponse(),
            'Anonymous requests have no session cookie to abuse — must bypass origin check.'
        );
    }

    public function testGetRequestSkipsCheck(): void
    {
        $subscriber = $this->buildSubscriber($this->authenticatedToken());
        $event = $this->buildEvent('GET', '/api/cvs', [
            'Origin' => 'http://evil.example.com',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull(
            $event->getResponse(),
            'GET requests are read-only — must bypass origin check.'
        );
    }

    public function testLoginEndpointExcluded(): void
    {
        $subscriber = $this->buildSubscriber($this->anonymousToken());
        $event = $this->buildEvent('POST', '/api/login', [
            'Origin' => 'http://evil.example.com',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull(
            $event->getResponse(),
            'Login is anonymous and protected by json_login content-type rule.'
        );
    }

    public function testRegistrationEndpointExcluded(): void
    {
        $subscriber = $this->buildSubscriber($this->anonymousToken());
        $event = $this->buildEvent('POST', '/api/registration', [
            'Origin' => 'http://evil.example.com',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testForgotPasswordEndpointExcluded(): void
    {
        $subscriber = $this->buildSubscriber($this->anonymousToken());
        $event = $this->buildEvent('POST', '/api/forgot-password', [
            'Origin' => 'http://evil.example.com',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testResetPasswordEndpointExcluded(): void
    {
        $subscriber = $this->buildSubscriber($this->anonymousToken());
        $event = $this->buildEvent('POST', '/api/reset-password', [
            'Origin' => 'http://evil.example.com',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testLogoutEndpointExcluded(): void
    {
        $subscriber = $this->buildSubscriber($this->authenticatedToken());
        $event = $this->buildEvent('POST', '/api/logout', [
            'Origin' => 'http://evil.example.com',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull(
            $event->getResponse(),
            'Logout is guarded by LogoutMethodRestrictionListener (POST-only).'
        );
    }

    public function testOAuthCallbackExcluded(): void
    {
        $subscriber = $this->buildSubscriber($this->anonymousToken());
        $event = $this->buildEvent('GET', '/login/check-google', [
            'Origin' => 'http://evil.example.com',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull(
            $event->getResponse(),
            'OAuth callbacks are guarded by provider-side state validation.'
        );
    }

    public function testOAuthConnectExcluded(): void
    {
        $subscriber = $this->buildSubscriber($this->anonymousToken());
        $event = $this->buildEvent('GET', '/connect/google', [
            'Origin' => 'http://evil.example.com',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testVerifyEmailEndpointExcluded(): void
    {
        $subscriber = $this->buildSubscriber($this->anonymousToken());
        $event = $this->buildEvent('GET', '/verify/email', [
            'Origin' => 'http://evil.example.com',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull(
            $event->getResponse(),
            'Verify-email is protected by VerifyEmailBundle signed URL.'
        );
    }

    public function testEmptyAllowedOriginDisablesCheck(): void
    {
        $subscriber = $this->buildSubscriber($this->authenticatedToken(), allowed: '');
        $event = $this->buildEvent('POST', '/api/cvs', [
            'Origin' => 'http://evil.example.com',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull(
            $event->getResponse(),
            'No allowlist configured → subscriber is a no-op (test/local setup).'
        );
    }

    public function testNonApiPathSkipped(): void
    {
        $subscriber = $this->buildSubscriber($this->authenticatedToken());
        $event = $this->buildEvent('POST', '/messenger/consume', [
            'Origin' => 'http://evil.example.com',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull(
            $event->getResponse(),
            'Cron endpoint is Bearer-auth, not session — must skip origin check.'
        );
    }
}
