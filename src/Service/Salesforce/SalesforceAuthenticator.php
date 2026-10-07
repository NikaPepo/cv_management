<?php

declare(strict_types=1);

namespace App\Service\Salesforce;

use App\Exception\Salesforce\SalesforceAuthException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OAuth 2.0 Client Credentials flow against the Salesforce token
 * endpoint. We use the Symfony HttpClient (no Salesforce bundle), with
 * form-encoded body and a short in-memory cache so a single export
 * that hits Account + Contact pays for one token round-trip.
 *
 * No secret ever leaves the backend. The response is parsed without
 * logging — access tokens are passed only to SalesforceClient, never
 * surfaced to controller, DTO, or HTTP responses.
 *
 * Not declared `readonly` because we hold a mutable in-memory token
 * cache (`$cachedToken`) that has to be assignable from inside the
 * class. The credentials passed through the constructor are still
 * immutable by convention.
 */
final class SalesforceAuthenticator
{
    /** @var array{access_token: string, instance_url: string, expires_at: int}|null */
    private ?array $cachedToken = null;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $loginUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $apiVersion,
    ) {
    }

    /**
     * @return array{access_token: string, instance_url: string}
     */
    public function authenticate(): array
    {
        if ($this->cachedToken !== null && $this->cachedToken['expires_at'] > time() + 30) {
            return $this->cachedToken;
        }

        $url = rtrim($this->loginUrl, '/') . '/services/oauth2/token';

        try {
            $response = $this->http->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query([
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]),
                // Per-request timeout keeps a hung Salesforce from
                // stalling the request thread indefinitely.
                'timeout' => 30,
            ]);
            $payload = $response->toArray(false);
        } catch (HttpExceptionInterface $e) {
            throw new SalesforceAuthException(
                sprintf('Salesforce token endpoint unreachable: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        $status = $response->getStatusCode();
        if ($status !== 200) {
            $errorText = $payload['error_description'] ?? $payload['error'] ?? 'unknown';
            throw new SalesforceAuthException(sprintf(
                'Salesforce auth failed (%d): %s',
                $status,
                $errorText,
            ));
        }

        if (!isset($payload['access_token'], $payload['instance_url'])) {
            throw new SalesforceAuthException(
                'Salesforce auth response missing access_token or instance_url.',
            );
        }

        // Salesforce default session lifetime is 2h. We refresh a touch
        // earlier so a long-running export doesn't hit a 401 mid-flight.
        $expiresAt = time() + 7200;
        $this->cachedToken = [
            'access_token' => (string) $payload['access_token'],
            'instance_url' => (string) $payload['instance_url'],
            'expires_at' => $expiresAt,
        ];

        return $this->cachedToken;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }
}