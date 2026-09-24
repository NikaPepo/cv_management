<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AttributeDefinition;
use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\Profile;
use App\Entity\ProfileAttribute;
use App\Enum\AttributeDataType;
use App\Enum\CvStatus;
use App\Repository\CvRepository;
use App\Repository\PositionRepository;
use App\Repository\ProfileAttributeRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * CV lifecycle. CVs are NOT JSON: the (profile, position) pair IS the CV;
 * the only mutable bits are status (DRAFT / PUBLISHED) and timestamps.
 *
 * Values are read from ProfileAttribute at view time (see CvViewAssembler).
 */
final readonly class CvService
{
    public function __construct(
        private CvRepository $cvRepository,
        private PositionRepository $positionRepository,
        private ProfileAttributeRepository $profileAttributeRepository,
        private PositionAccessEvaluator $accessEvaluator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function createForPosition(Profile $profile, int $positionId): Cv
    {
        $position = $this->positionRepository->find($positionId);
        if ($position === null) {
            throw new UnprocessableEntityHttpException('positionId not found.');
        }

        if (!$this->accessEvaluator->isAccessible($position, $profile)) {
            throw new AccessDeniedHttpException('You do not satisfy the position access rules.');
        }

        $existing = $this->cvRepository->findOneByProfileAndPosition($profile, $position);
        if ($existing !== null) {
            throw new ConflictHttpException('CV for this position already exists.');
        }

        $cv = new Cv();
        $cv->setProfile($profile);
        $cv->setPosition($position);
        $cv->setStatus(CvStatus::Draft);

        try {
            $this->cvRepository->save($cv);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('CV for this position already exists.');
        }

        return $cv;
    }

    public function publish(Cv $cv, Profile $profile): Cv
    {
        $this->assertOwner($cv, $profile);

        if (!$this->accessEvaluator->isAccessible($cv->getPosition(), $profile)) {
            throw new AccessDeniedHttpException('Position access lost; cannot publish.');
        }

        // Pre-load every ProfileAttribute for this profile in ONE query
        // (the repository already eager-loads definition + options), then
        // build a map by attributeDefinitionId so the required-attribute
        // loop below does zero extra DB roundtrips.
        $rows = $this->profileAttributeRepository->findAllForProfile($profile);
        $byDefinition = [];
        foreach ($rows as $row) {
            $byDefinition[$row->getAttributeDefinition()->getId()] = $row;
        }

        foreach ($cv->getPosition()->getAttributes() as $pa) {
            $def = $pa->getAttributeDefinition();
            if (!$def->isRequired()) {
                continue;
            }
            $row = $byDefinition[$def->getId()] ?? null;
            $value = $row === null ? null : $this->extractTypedValue($row);
            // An attribute is "empty" only when its typed column is null
            // (or '' for strings/text). boolean=false is an explicit value
            // and must NOT be treated as empty.
            if ($value === null || $value === '') {
                throw new UnprocessableEntityHttpException(sprintf(
                    'Required attribute "%s" is empty.',
                    $def->getName()
                ));
            }
        }

        $cv->setStatus(CvStatus::Published);
        $cv->setPublishedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $cv;
    }

    public function delete(Cv $cv, Profile $profile, bool $isAdmin): void
    {
        if (!$isAdmin && $cv->getProfile()->getId() !== $profile->getId()) {
            throw new AccessDeniedHttpException('Not your CV.');
        }
        $this->entityManager->remove($cv);
        $this->entityManager->flush();
    }

    /**
     * Whether the position's access rules are still met. If not, the CV is
     * hidden (not deleted) per the assignment — the candidate loses access,
     * the recruiter stops seeing it, the row stays.
     */
    public function isAccessible(Cv $cv, ?Profile $viewer = null): bool
    {
        return $this->accessEvaluator->isAccessible($cv->getPosition(), $viewer);
    }

    private function assertOwner(Cv $cv, Profile $profile): void
    {
        if ($cv->getProfile()->getId() !== $profile->getId()) {
            throw new AccessDeniedHttpException('Not your CV.');
        }
    }

    private function extractTypedValue(ProfileAttribute $row): mixed
    {
        $def = $row->getAttributeDefinition();
        return match ($def->getDataType()) {
            AttributeDataType::String => $row->getStringValue(),
            AttributeDataType::Text => $row->getMarkdownText(),
            AttributeDataType::Numeric => $row->getNumericValue(),
            AttributeDataType::Date => $row->getDateValue()?->format('Y-m-d'),
            AttributeDataType::Period => $row->getPeriodStart()?->format('Y-m-d'),
            AttributeDataType::Boolean => $row->getBooleanValue(),
            AttributeDataType::Image => $row->getImageUrl(),
            AttributeDataType::OneOfMany => $row->getSelectedOption()?->getId(),
        };
    }
}
