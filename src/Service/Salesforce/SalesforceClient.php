<?php

declare(strict_types=1);

namespace App\Service\Salesforce;

use App\Exception\Salesforce\SalesforceApiException;
use App\Exception\Salesforce\SalesforceAuthException;
use App\Exception\Salesforce\SalesforceStaleRecordException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Low-level HTTP wrapper around the four sObjects endpoints the
 * integration needs. No mapping, no duplicate logic — only transport.
 *
 * Every method calls SalesforceAuthenticator::authenticate() so a single
 * export that hits Account + Contact makes one token round-trip
 * (cached for the lifetime of the request).
 */
/**
 * Not `final` so PHPUnit can mock it directly from the service-layer
 * test suite. The constructor properties are still readonly by
 * convention.
 */
class SalesforceClient
{
    public function __construct(
        private HttpClientInterface $http,
        private SalesforceAuthenticator $authenticator,
    ) {
    }

    /**
     * @return array{id: string, success: bool, errors: list<array<string, mixed>>}
     */
    public function createAccount(array $payload): array
    {
        return $this->post('/sobjects/Account/', $payload);
    }

    /**
     * @return array{id: string, success: bool, errors: list<array<string, mixed>>}
     */
    public function createContact(array $payload): array
    {
        return $this->post('/sobjects/Contact/', $payload);
    }

    /**
     * PATCH on the sObject REST endpoint. Salesforce returns 204 No
     * Content on success — Symfony's HTTP client surfaces a 2xx with
     * empty body, so we don't try to parse JSON.
     *
     * Returns the Salesforce sObject id passed via $id.
     *
     * @return array{id: string, statusCode: int}
     */
    public function updateAccount(string $id, array $payload): array
    {
        $statusCode = $this->patch(sprintf('/sobjects/Account/%s', rawurlencode($id)), $payload);
        return ['id' => $id, 'statusCode' => $statusCode];
    }

    /**
     * @return array{id: string, statusCode: int}
     */
    public function updateContact(string $id, array $payload): array
    {
        $statusCode = $this->patch(sprintf('/sobjects/Contact/%s', rawurlencode($id)), $payload);
        return ['id' => $id, 'statusCode' => $statusCode];
    }

    /**
     * @return array{id: string, success: bool, errors: list<array<string, mixed>>}
     */
    private function post(string $path, array $payload): array
    {
        try {
            $auth = $this->authenticator->authenticate();
            $response = $this->http->request(
                'POST',
                rtrim($auth['instance_url'], '/') . '/services/data/' . $this->authenticator->getApiVersion() . $path,
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $auth['access_token'],
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $payload,
                    'timeout' => 30,
                ],
            );
        } catch (HttpExceptionInterface $e) {
            throw new SalesforceApiException(
                sprintf('Salesforce transport error on %s: %s', $path, $e->getMessage()),
                0,
                $path,
                [],
                $e,
            );
        } catch (SalesforceAuthException $e) {
            throw $e;
        }

        return $this->parseSObjectResponse($response, $path);
    }

    private function patch(string $path, array $payload): int
    {
        try {
            $auth = $this->authenticator->authenticate();
            $response = $this->http->request(
                'PATCH',
                rtrim($auth['instance_url'], '/') . '/services/data/' . $this->authenticator->getApiVersion() . $path,
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $auth['access_token'],
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $payload,
                    'timeout' => 30,
                ],
            );
        } catch (HttpExceptionInterface $e) {
            throw new SalesforceApiException(
                sprintf('Salesforce transport error on %s: %s', $path, $e->getMessage()),
                0,
                $path,
                [],
                $e,
            );
        }

        $status = $response->getStatusCode();
        if ($status === 404) {
            throw new SalesforceStaleRecordException(
                sprintf('Salesforce returned 404 for %s — record was deleted.', $path),
            );
        }
        if ($status < 200 || $status >= 300) {
            $errors = [];
            try {
                $body = $response->toArray(false);
                if (isset($body[0]) && is_array($body[0])) {
                    $errors = $body;
                }
            } catch (\Throwable) {
                // Non-JSON body — leave empty.
            }
            throw new SalesforceApiException(
                sprintf('Salesforce PATCH %s failed: %d', $path, $status),
                $status,
                $path,
                $errors,
            );
        }
        return $status;
    }

    /**
     * @return array{id: string, success: bool, errors: list<array<string, mixed>>}
     */
    private function parseSobjectResponse(\Symfony\Contracts\HttpClient\ResponseInterface $response, string $path): array
    {
        $status = $response->getStatusCode();
        $body = [];
        try {
            $body = $response->toArray(false);
        } catch (\Throwable) {
            // Empty / unparseable body, keep error array.
        }
        if ($status === 201 && isset($body['id'])) {
            return ['id' => (string) $body['id'], 'success' => true, 'errors' => []];
        }
        if ($status >= 400) {
            // Normalize error shape — Salesforce returns either [{...}]
            // or {message, errorCode}.
            $errors = $body;
            if (isset($body[0]) && is_array($body[0])) {
                $errors = $body;
            }
            throw new SalesforceApiException(
                sprintf('Salesforce POST %s failed: %d', $path, $status),
                $status,
                $path,
                is_array($errors) ? $errors : [],
            );
        }
        throw new SalesforceApiException(
            sprintf('Salesforce POST %s unexpected status: %d', $path, $status),
            $status,
            $path,
            [],
        );
    }
}