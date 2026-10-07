<?php

declare(strict_types=1);

namespace App\Exception\Salesforce;

/**
 * Salesforce returned 404 for an Account/Contact we still have IDs for
 * in SalesforceConnection. Signals that the CRM record was deleted out
 * of band. The service clears the stale IDs so the next Export creates
 * fresh records; the controller turns it into a clear, action-oriented
 * 502 response so the user knows to re-export.
 */
final class SalesforceStaleRecordException extends SalesforceException
{
}