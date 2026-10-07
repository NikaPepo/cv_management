<?php

declare(strict_types=1);

namespace App\Exception\Salesforce;

/**
 * OAuth Client Credentials flow failed — invalid client, expired
 * run-as user, network error talking to the token endpoint, etc.
 * Controller renders this as 502 with a generic message.
 */
final class SalesforceAuthException extends SalesforceException
{
}