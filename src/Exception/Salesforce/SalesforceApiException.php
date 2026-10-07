<?php

declare(strict_types=1);

namespace App\Exception\Salesforce;

/**
 * Non-2xx from a Salesforce REST call. Carries statusCode + endpoint
 * path so the service layer can decide whether to surface a stale-id
 * recovery (404) or just bubble a 502.
 */
final class SalesforceApiException extends SalesforceException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $endpoint,
        /** @var list<array<string, mixed>> */
        public readonly array $salesforceErrors = [],
    ) {
        parent::__construct($message);
    }
}