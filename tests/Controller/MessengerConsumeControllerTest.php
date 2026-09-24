<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Service\MessengerConsumeService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Behavioural contract for the cron-triggered Messenger consume endpoint.
 *
 * These tests exercise the HTTP surface and the firewall rules; the
 * underlying Symfony Messenger `Worker` is not actually run (the
 * `MessengerConsumeService` is replaced with a PHPUnit mock so tests
 * don't need a real queue).
 */
final class MessengerConsumeControllerTest extends WebTestCase
{
    private const ROUTE = '/messenger/consume';
    private const VALID_TOKEN = 'test-cron-token-please-rotate';

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    /**
     * Returns a KernelBrowser with a MessengerConsumeService that
     * reports the given result code from consume(). Using createMock
     * instead of an anonymous subclass keeps PHPStan happy (no
     * MissingParentConstructorCall warning) and avoids the readonly
     * pitfall of trying to extend the production service.
     */
    private function buildClientWithConsumerStub(string $resultCode): KernelBrowser
    {
        $client = self::createClient();

        /** @var MessengerConsumeService&MockObject $stub */
        $stub = $this->createMock(MessengerConsumeService::class);
        $stub->method('consume')->willReturn($resultCode);

        self::getContainer()->set(MessengerConsumeService::class, $stub);

        return $client;
    }

    public function testPostWithoutAuthorizationReturns401(): void
    {
        $client = self::createClient();
        $client->request('POST', self::ROUTE);

        self::assertSame(401, $client->getResponse()->getStatusCode());

        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('unauthorized', $body['status'] ?? null);
    }

    public function testPostWithWrongBearerTokenReturns401(): void
    {
        $client = self::createClient();
        $client->request(
            'POST',
            self::ROUTE,
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::VALID_TOKEN . 'tampered'],
        );

        self::assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testPostWithNonBearerSchemeReturns401(): void
    {
        $client = self::createClient();
        $client->request(
            'POST',
            self::ROUTE,
            server: ['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('user:pass')],
        );

        self::assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testGetReturns405(): void
    {
        $client = self::createClient();
        $client->request('GET', self::ROUTE);

        self::assertSame(405, $client->getResponse()->getStatusCode());
    }

    public function testPutReturns405(): void
    {
        $client = self::createClient();
        $client->request('PUT', self::ROUTE);

        self::assertSame(405, $client->getResponse()->getStatusCode());
    }

    public function testPostWithValidTokenReturns200(): void
    {
        $client = $this->buildClientWithConsumerStub(MessengerConsumeService::RESULT_OK);
        $client->request(
            'POST',
            self::ROUTE,
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::VALID_TOKEN],
        );

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('ok', $body['status'] ?? null);
    }

    public function testResponseBodyNeverContainsToken(): void
    {
        $client = $this->buildClientWithConsumerStub(MessengerConsumeService::RESULT_OK);
        $client->request(
            'POST',
            self::ROUTE,
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::VALID_TOKEN],
        );

        $content = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString(self::VALID_TOKEN, $content);
    }

    public function testEndpointDoesNotRequireUserSession(): void
    {
        // No login flow, no session cookie, no user token. Just hit the
        // route with a valid machine token.
        $client = $this->buildClientWithConsumerStub(MessengerConsumeService::RESULT_OK);
        $client->request(
            'POST',
            self::ROUTE,
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::VALID_TOKEN],
        );

        // The firewall pattern `^/messenger/consume$` has `security: false`,
        // so this should be reachable without going through the JSON login
        // firewall. A non-401 status proves that.
        self::assertNotSame(401, $client->getResponse()->getStatusCode());
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    /**
     * Service stub returns RESULT_BUSY → still HTTP 200 with body
     * indicating busy (cron tick was not wasted but no work was done).
     */
    public function testBusyResultMapsToHttp200WithBusyStatus(): void
    {
        $client = $this->buildClientWithConsumerStub(MessengerConsumeService::RESULT_BUSY);
        $client->request(
            'POST',
            self::ROUTE,
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::VALID_TOKEN],
        );

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('busy', $body['status'] ?? null);
    }

    public function testServiceFailureMapsToHttp500WithoutLeakingDetails(): void
    {
        $client = $this->buildClientWithConsumerStub(MessengerConsumeService::RESULT_FAILED);
        $client->request(
            'POST',
            self::ROUTE,
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::VALID_TOKEN],
        );

        self::assertSame(500, $client->getResponse()->getStatusCode());

        $content = (string) $client->getResponse()->getContent();
        // The token must never appear in error responses.
        self::assertStringNotContainsString(self::VALID_TOKEN, $content);
        // Stack traces or class names must not leak.
        self::assertStringNotContainsString('Throwable', $content);
        self::assertStringNotContainsString('MessengerConsumeService', $content);
    }
}