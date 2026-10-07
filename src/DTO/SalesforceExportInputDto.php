<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload for POST /api/profile/salesforce — the "Export to Salesforce"
 * modal in the React frontend.
 *
 * - accountName: required; becomes Account.Name in Salesforce.
 * - industry: optional; becomes Account.Industry.
 * - lastName: required when the current Profile has none. Salesforce
 *   Contact.LastName is mandatory on create — we validate server-side
 *   because the React form may have bypassed its own validation.
 * - notes: optional; becomes Contact.Description.
 * - title: optional; becomes Contact.Title (Salesforce title field).
 * - phone: optional; becomes Contact.Phone. Intentionally not enforced
 *   as E.164 — the project does not validate phone shape anywhere else,
 *   so a max-length cap is enough to protect the Salesforce sObject.
 *
 * Email and FirstName are taken from the current Profile/User, never
 * accepted from the client. We don't trust a client-supplied email
 * for an Account/Contact export.
 */
final readonly class SalesforceExportInputDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Company name is required.')]
        #[Assert\Length(max: 255)]
        public string $accountName,

        #[Assert\Length(max: 80)]
        public ?string $industry = null,

        #[Assert\Length(max: 80)]
        public ?string $lastName = null,

        #[Assert\Length(max: 32000)]
        public ?string $notes = null,

        #[Assert\Length(max: 128)]
        public ?string $title = null,

        #[Assert\Length(max: 50)]
        public ?string $phone = null,
    ) {
    }
}