<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SalesforceConnectionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tracks the Salesforce Account + Contact created on the first Export
 * for a given Profile. The unique profile_id index + 1:1 mapping is
 * what makes subsequent exports idempotent: PATCH the same records
 * instead of POSTing new ones.
 *
 * No credentials live here. Only Salesforce IDs (which the developer
 * org already considers public-ish identifiers for individual rows).
 */
#[ORM\Entity(repositoryClass: SalesforceConnectionRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_SALESFORCE_CONNECTION_PROFILE', columns: ['profile_id'])]
#[ORM\Index(name: 'IDX_SALESFORCE_CONNECTION_ACCOUNT', columns: ['salesforce_account_id'])]
class SalesforceConnection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Profile::class)]
    #[ORM\JoinColumn(unique: true, nullable: false, onDelete: 'CASCADE')]
    private Profile $profile;

    /**
     * Salesforce Account ID is required because Export is meaningless
     * without at least an Account. Contact is nullable to support the
     * partial-failure scenario where Account creation succeeded but
     * Contact creation failed (see SalesforceService::exportCurrentUser).
     */
    #[ORM\Column(length: 32)]
    private string $salesforceAccountId;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $salesforceContactId = null;

    #[ORM\Column]
    private \DateTimeImmutable $lastExportedAt;

    /**
     * Last error message from Salesforce. Persisted so a recruiter /
     * admin debugging an integration issue can see a useful message
     * without a second API round-trip. Cleared on the next successful
     * export.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    public function __construct(Profile $profile, string $salesforceAccountId)
    {
        $this->profile = $profile;
        $this->salesforceAccountId = $salesforceAccountId;
        $this->lastExportedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): Profile
    {
        return $this->profile;
    }

    public function getSalesforceAccountId(): string
    {
        return $this->salesforceAccountId;
    }

    public function getSalesforceContactId(): ?string
    {
        return $this->salesforceContactId;
    }

    public function setSalesforceContactId(?string $salesforceContactId): static
    {
        $this->salesforceContactId = $salesforceContactId;
        return $this;
    }

    public function getLastExportedAt(): \DateTimeImmutable
    {
        return $this->lastExportedAt;
    }

    public function touchLastExportedAt(): static
    {
        $this->lastExportedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): static
    {
        $this->lastError = $lastError;
        return $this;
    }
}