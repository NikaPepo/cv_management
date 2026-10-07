<?php

declare(strict_types=1);

namespace App\Exception\Salesforce;

/**
 * Base for everything Salesforce-related. We keep these exceptions as
 * a separate namespace so the controller can render them as a single
 * 502 with a generic message and never leak Salesforce internals (URLs,
 * IDs in error context, etc.) to the React client.
 */
abstract class SalesforceException extends \RuntimeException
{
}