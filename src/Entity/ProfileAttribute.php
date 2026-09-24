<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttributeDataType;
use App\Repository\ProfileAttributeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfileAttributeRepository::class)]
#[ORM\Table(name: 'profile_attribute')]
#[ORM\UniqueConstraint(
    name: 'UNIQ_PROFILE_ATTRIBUTE_PROFILE_DEF',
    columns: ['profile_id', 'attribute_definition_id']
)]
#[ORM\Index(name: 'IDX_PROFILE_ATTRIBUTE_DEFINITION', columns: ['attribute_definition_id'])]
class ProfileAttribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profile::class, inversedBy: 'attributes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Profile $profile;

    #[ORM\ManyToOne(targetEntity: AttributeDefinition::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AttributeDefinition $attributeDefinition;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    // Typed value columns — only one is populated per row, matching dataType.
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stringValue = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $markdownText = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: true)]
    private ?string $numericValue = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateValue = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $periodStart = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $periodEnd = null;

    #[ORM\Column(nullable: true)]
    private ?bool $booleanValue = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\ManyToOne(targetEntity: AttributeOption::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AttributeOption $selectedOption = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): Profile
    {
        return $this->profile;
    }

    public function setProfile(Profile $profile): static
    {
        $this->profile = $profile;
        return $this;
    }

    public function getAttributeDefinition(): AttributeDefinition
    {
        return $this->attributeDefinition;
    }

    public function setAttributeDefinition(AttributeDefinition $attributeDefinition): static
    {
        $this->attributeDefinition = $attributeDefinition;
        return $this;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Returns true if no typed value column is populated for this row.
     * Drives the red-highlight behavior required by the assignment.
     */
    public function isEmpty(): bool
    {
        return match ($this->attributeDefinition->getDataType()) {
            AttributeDataType::String => $this->stringValue === null || $this->stringValue === '',
            AttributeDataType::Text => $this->markdownText === null || $this->markdownText === '',
            AttributeDataType::Image => $this->imageUrl === null || $this->imageUrl === '',
            AttributeDataType::Numeric => $this->numericValue === null,
            AttributeDataType::Date => $this->dateValue === null,
            AttributeDataType::Period => $this->periodStart === null && $this->periodEnd === null,
            AttributeDataType::Boolean => $this->booleanValue === null,
            AttributeDataType::OneOfMany => $this->selectedOption === null,
        };
    }

    public function getStringValue(): ?string
    {
        return $this->stringValue;
    }

    public function setStringValue(?string $value): static
    {
        $this->stringValue = $value;
        $this->touch();
        return $this;
    }

    public function getMarkdownText(): ?string
    {
        return $this->markdownText;
    }

    public function setMarkdownText(?string $value): static
    {
        $this->markdownText = $value;
        $this->touch();
        return $this;
    }

    public function getNumericValue(): ?string
    {
        return $this->numericValue;
    }

    public function setNumericValue(?string $value): static
    {
        $this->numericValue = $value;
        $this->touch();
        return $this;
    }

    public function getDateValue(): ?\DateTimeImmutable
    {
        return $this->dateValue;
    }

    public function setDateValue(?\DateTimeImmutable $value): static
    {
        $this->dateValue = $value;
        $this->touch();
        return $this;
    }

    public function getPeriodStart(): ?\DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(?\DateTimeImmutable $value): static
    {
        $this->periodStart = $value;
        $this->touch();
        return $this;
    }

    public function getPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(?\DateTimeImmutable $value): static
    {
        $this->periodEnd = $value;
        $this->touch();
        return $this;
    }

    public function getBooleanValue(): ?bool
    {
        return $this->booleanValue;
    }

    public function setBooleanValue(?bool $value): static
    {
        $this->booleanValue = $value;
        $this->touch();
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $value): static
    {
        $this->imageUrl = $value;
        $this->touch();
        return $this;
    }

    public function getSelectedOption(): ?AttributeOption
    {
        return $this->selectedOption;
    }

    public function setSelectedOption(?AttributeOption $option): static
    {
        $this->selectedOption = $option;
        $this->touch();
        return $this;
    }
}