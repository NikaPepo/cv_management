<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AttributeDefinition;
use App\Enum\AttributeDataType;
use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\PositionAttribute;
use App\Enum\CvStatus;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Computes aggregated attribute statistics for one Position, intended
 * for the Odoo integration. Candidate population follows the existing
 * recruiter-visible rule (published CVs only — see CvRepository::
 * findPublishedForPosition). Aggregations are computed in SQL; we
 * never load Profile rows into PHP.
 *
 * Privacy: small buckets (text/option values with frequency < K) are
 * suppressed so that the response cannot be used to single out a
 * candidate. K is configurable per service instance.
 */
final class OdooAggregationService
{
    private const K_ANONYMITY = 5;
    private const TOP_N_TEXT = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly int $kAnonymity = self::K_ANONYMITY,
    ) {
    }

    /**
     * @return array{
     *     position: array<string, mixed>,
     *     attributes: list<array<string, mixed>>,
     *     summary: array<string, int>,
     *     generatedAt: string
     * }
     */
    public function aggregate(Position $position): array
    {
        $conn = $this->em->getConnection();

        return [
            'position' => $this->presentPosition($position),
            'attributes' => array_map(
                fn (PositionAttribute $pa) => $this->aggregateAttribute($conn, $pa->getAttributeDefinition(), $position),
                $position->getAttributes()->toArray()
            ),
            'summary' => $this->summary($conn, $position),
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function presentPosition(Position $position): array
    {
        return [
            'id' => $position->getId(),
            'title' => $position->getTitle(),
            'company' => $position->getCompany(),
            'level' => $position->getLevel()?->value,
            'isPublic' => $position->isPublic(),
            'shortDescription' => $position->getShortDescription(),
            'maxProjects' => $position->getMaxProjects(),
            'projectTagFilter' => $position->getProjectTagFilter(),
        ];
    }

    /**
     * The published-CV count. Anything more elaborate (per-candidate
     * counts, like counts, etc.) is intentionally not included — the
     * Odoo view is meant to be a statistical summary, not a per-row
     * export.
     *
     * @return array<string, int>
     */
    private function summary(Connection $conn, Position $position): array
    {
        $totalCandidates = (int) $conn->fetchOne(
            'SELECT COUNT(DISTINCT cv.profile_id)
               FROM cv
              WHERE cv.position_id = :pos
                AND cv.status = :status',
            ['pos' => $position->getId(), 'status' => CvStatus::Published->value]
        );

        return ['totalCandidates' => $totalCandidates];
    }

    /** @return array<string, mixed> */
    private function aggregateAttribute(Connection $conn, AttributeDefinition $def, Position $position): array
    {
        $base = [
            'attributeDefinitionId' => $def->getId(),
            'name' => $def->getName(),
            'dataType' => $def->getDataType()->value,
            'required' => $def->isRequired(),
        ];

        // Options are useful for OneOfMany rendering in Odoo.
        if ($def->getDataType() === AttributeDataType::OneOfMany) {
            $base['options'] = array_map(
                static fn ($o) => ['id' => $o->getId(), 'value' => $o->getValue()],
                $def->getOptions()->toArray()
            );
        } else {
            $base['options'] = null;
        }

        $base['aggregation'] = match ($def->getDataType()) {
            AttributeDataType::Numeric => $this->aggregateNumeric($conn, $def, $position),
            AttributeDataType::String, AttributeDataType::Text => $this->aggregateText($conn, $def, $position),
            AttributeDataType::Boolean => $this->aggregateBoolean($conn, $def, $position),
            AttributeDataType::OneOfMany => $this->aggregateOneOfMany($conn, $def, $position),
            AttributeDataType::Date => $this->aggregateDate($conn, $def, $position),
            AttributeDataType::Period => $this->aggregatePeriod($conn, $def, $position),
            AttributeDataType::Image => $this->aggregateImage($conn, $def, $position),
        };

        return $base;
    }

    /** @return array<string, mixed> */
    private function aggregateNumeric(Connection $conn, AttributeDefinition $def, Position $position): array
    {
        $row = $conn->fetchAssociative(
            'SELECT
                COUNT(pa.numeric_value)             AS cnt,
                AVG(pa.numeric_value)::float8       AS avg,
                MIN(pa.numeric_value)::float8       AS min,
                MAX(pa.numeric_value)::float8       AS max
               FROM profile_attribute pa
               JOIN cv ON cv.profile_id = pa.profile_id
              WHERE cv.position_id = :pos
                AND cv.status = :status
                AND pa.attribute_definition_id = :def
                AND pa.numeric_value IS NOT NULL',
            [
                'pos' => $position->getId(),
                'status' => CvStatus::Published->value,
                'def' => $def->getId(),
            ]
        );

        return [
            'count' => (int) ($row['cnt'] ?? 0),
            'avg' => $row['avg'] !== null ? (float) $row['avg'] : null,
            'min' => $row['min'] !== null ? (float) $row['min'] : null,
            'max' => $row['max'] !== null ? (float) $row['max'] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function aggregateText(Connection $conn, AttributeDefinition $def, Position $position): array
    {
        $column = $def->getDataType() === AttributeDataType::Text
            ? 'pa.markdown_text'
            : 'pa.string_value';

        $rows = $conn->fetchAllAssociative(
            "SELECT $column AS value, COUNT(*) AS c
               FROM profile_attribute pa
               JOIN cv ON cv.profile_id = pa.profile_id
              WHERE cv.position_id = :pos
                AND cv.status = :status
                AND pa.attribute_definition_id = :def
                AND $column IS NOT NULL
                AND $column <> ''
              GROUP BY $column
              ORDER BY c DESC, value ASC
              LIMIT " . self::TOP_N_TEXT,
            [
                'pos' => $position->getId(),
                'status' => CvStatus::Published->value,
                'def' => $def->getId(),
            ]
        );

        $total = (int) $conn->fetchOne(
            "SELECT COUNT(*)
               FROM profile_attribute pa
               JOIN cv ON cv.profile_id = pa.profile_id
              WHERE cv.position_id = :pos
                AND cv.status = :status
                AND pa.attribute_definition_id = :def
                AND $column IS NOT NULL
                AND $column <> ''",
            [
                'pos' => $position->getId(),
                'status' => CvStatus::Published->value,
                'def' => $def->getId(),
            ]
        );

        $topValues = [];
        $suppressedCount = 0;
        foreach ($rows as $row) {
            $count = (int) $row['c'];
            if ($count < $this->kAnonymity) {
                $suppressedCount += $count;
                continue;
            }
            $topValues[] = ['value' => (string) $row['value'], 'count' => $count];
        }

        return [
            'count' => $total,
            'topValues' => $topValues,
            'suppressedCount' => $suppressedCount,
        ];
    }

    /** @return array<string, mixed> */
    private function aggregateBoolean(Connection $conn, AttributeDefinition $def, Position $position): array
    {
        $row = $conn->fetchAssociative(
            'SELECT
                COUNT(*) FILTER (WHERE pa.boolean_value IS NOT NULL) AS cnt,
                COUNT(*) FILTER (WHERE pa.boolean_value = TRUE)      AS t,
                COUNT(*) FILTER (WHERE pa.boolean_value = FALSE)     AS f
               FROM profile_attribute pa
               JOIN cv ON cv.profile_id = pa.profile_id
              WHERE cv.position_id = :pos
                AND cv.status = :status
                AND pa.attribute_definition_id = :def',
            [
                'pos' => $position->getId(),
                'status' => CvStatus::Published->value,
                'def' => $def->getId(),
            ]
        );

        $trueCount = (int) ($row['t'] ?? 0);
        $falseCount = (int) ($row['f'] ?? 0);

        return [
            'count' => (int) ($row['cnt'] ?? 0),
            'trueCount' => $trueCount < $this->kAnonymity ? null : $trueCount,
            'falseCount' => $falseCount < $this->kAnonymity ? null : $falseCount,
        ];
    }

    /** @return array<string, mixed> */
    private function aggregateOneOfMany(Connection $conn, AttributeDefinition $def, Position $position): array
    {
        $rows = $conn->fetchAllAssociative(
            'SELECT opt.id AS option_id, opt.value AS option_value, COUNT(*) AS c
               FROM profile_attribute pa
               JOIN attribute_option opt ON opt.id = pa.selected_option_id
               JOIN cv ON cv.profile_id = pa.profile_id
              WHERE cv.position_id = :pos
                AND cv.status = :status
                AND pa.attribute_definition_id = :def
                AND pa.selected_option_id IS NOT NULL
              GROUP BY opt.id, opt.value
              ORDER BY c DESC, opt.value ASC',
            [
                'pos' => $position->getId(),
                'status' => CvStatus::Published->value,
                'def' => $def->getId(),
            ]
        );

        $total = (int) $conn->fetchOne(
            'SELECT COUNT(*)
               FROM profile_attribute pa
               JOIN cv ON cv.profile_id = pa.profile_id
              WHERE cv.position_id = :pos
                AND cv.status = :status
                AND pa.attribute_definition_id = :def
                AND pa.selected_option_id IS NOT NULL',
            [
                'pos' => $position->getId(),
                'status' => CvStatus::Published->value,
                'def' => $def->getId(),
            ]
        );

        $options = [];
        $suppressedCount = 0;
        foreach ($rows as $row) {
            $count = (int) $row['c'];
            $entry = [
                'id' => (int) $row['option_id'],
                'value' => (string) $row['option_value'],
                'count' => $count,
            ];
            if ($count < $this->kAnonymity) {
                $suppressedCount += $count;
                continue;
            }
            $options[] = $entry;
        }

        return [
            'count' => $total,
            'options' => $options,
            'suppressedCount' => $suppressedCount,
        ];
    }

    /** @return array<string, mixed> */
    private function aggregateDate(Connection $conn, AttributeDefinition $def, Position $position): array
    {
        // Bucket by year by default; recruiters usually care about
        // recency, not exact day, and a year is always k-anonymous
        // for any position that has > K candidates.
        $rows = $conn->fetchAllAssociative(
            "SELECT EXTRACT(YEAR FROM pa.date_value)::int AS year, COUNT(*) AS c
               FROM profile_attribute pa
               JOIN cv ON cv.profile_id = pa.profile_id
              WHERE cv.position_id = :pos
                AND cv.status = :status
                AND pa.attribute_definition_id = :def
                AND pa.date_value IS NOT NULL
              GROUP BY year
              ORDER BY year ASC",
            [
                'pos' => $position->getId(),
                'status' => CvStatus::Published->value,
                'def' => $def->getId(),
            ]
        );

        $total = (int) array_sum(array_column($rows, 'c'));
        $buckets = [];
        $suppressedCount = 0;
        foreach ($rows as $row) {
            $count = (int) $row['c'];
            if ($count < $this->kAnonymity) {
                $suppressedCount += $count;
                continue;
            }
            $buckets[] = ['period' => (string) $row['year'], 'count' => $count];
        }

        return [
            'count' => $total,
            'buckets' => $buckets,
            'suppressedCount' => $suppressedCount,
        ];
    }

    /** @return array<string, mixed> */
    private function aggregatePeriod(Connection $conn, AttributeDefinition $def, Position $position): array
    {
        // Duration only makes sense when both endpoints are set AND
        // end >= start. The application flow (SetProfileAttributeDto)
        // and the database schema (no CHECK constraint on period_end
        // vs period_start) both allow inverted intervals, so we
        // exclude them from the duration aggregates here. Inverted
        // rows are still counted in `count` and in `closedCount`
        // because they are non-empty rows the candidate entered, but
        // they are excluded from avg/min/max so the stats stay
        // meaningful.
        $row = $conn->fetchAssociative(
            'SELECT
                COUNT(*) FILTER (WHERE pa.period_start IS NOT NULL AND pa.period_end IS NOT NULL) AS closed,
                COUNT(*) FILTER (WHERE pa.period_start IS NOT NULL AND pa.period_end IS NULL)     AS open_ended,
                COUNT(*) FILTER (WHERE pa.period_start IS NOT NULL OR pa.period_end IS NOT NULL)  AS total,
                AVG(EXTRACT(EPOCH FROM (pa.period_end::timestamp - pa.period_start::timestamp)) / 86400)
                    FILTER (WHERE pa.period_start IS NOT NULL AND pa.period_end IS NOT NULL
                            AND pa.period_end >= pa.period_start)::float8 AS avg_days,
                MIN(EXTRACT(EPOCH FROM (pa.period_end::timestamp - pa.period_start::timestamp)) / 86400)
                    FILTER (WHERE pa.period_start IS NOT NULL AND pa.period_end IS NOT NULL
                            AND pa.period_end >= pa.period_start)::float8 AS min_days,
                MAX(EXTRACT(EPOCH FROM (pa.period_end::timestamp - pa.period_start::timestamp)) / 86400)
                    FILTER (WHERE pa.period_start IS NOT NULL AND pa.period_end IS NOT NULL
                            AND pa.period_end >= pa.period_start)::float8 AS max_days
               FROM profile_attribute pa
               JOIN cv ON cv.profile_id = pa.profile_id
              WHERE cv.position_id = :pos
                AND cv.status = :status
                AND pa.attribute_definition_id = :def',
            [
                'pos' => $position->getId(),
                'status' => CvStatus::Published->value,
                'def' => $def->getId(),
            ]
        );

        return [
            'count' => (int) ($row['total'] ?? 0),
            'closedCount' => (int) ($row['closed'] ?? 0),
            'openEndedCount' => (int) ($row['open_ended'] ?? 0),
            'avgDays' => $row['avg_days'] !== null ? (float) $row['avg_days'] : null,
            'minDays' => $row['min_days'] !== null ? (float) $row['min_days'] : null,
            'maxDays' => $row['max_days'] !== null ? (float) $row['max_days'] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function aggregateImage(Connection $conn, AttributeDefinition $def, Position $position): array
    {
        // Do NOT return image URLs (could leak PII). Only the count of
        // candidates who have answered, and the count who haven't.
        $row = $conn->fetchAssociative(
            'SELECT
                COUNT(*) FILTER (WHERE pa.image_url IS NOT NULL AND pa.image_url <> \'\') AS answered,
                COUNT(*) FILTER (WHERE pa.image_url IS NULL OR pa.image_url = \'\')       AS empty
               FROM profile_attribute pa
               JOIN cv ON cv.profile_id = pa.profile_id
              WHERE cv.position_id = :pos
                AND cv.status = :status
                AND pa.attribute_definition_id = :def',
            [
                'pos' => $position->getId(),
                'status' => CvStatus::Published->value,
                'def' => $def->getId(),
            ]
        );

        return [
            'count' => (int) ($row['answered'] ?? 0),
            'emptyCount' => (int) ($row['empty'] ?? 0),
        ];
    }
}
