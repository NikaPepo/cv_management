<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cv;
use App\Entity\Position;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Aggregate queries the assignment requires on the main page:
 *   - CVs created in the last 24h
 *   - total positions
 *   - total candidates (ROLE_CANDIDATE)
 *   - total recruiters (ROLE_RECRUITER)
 *   - total submitted (PUBLISHED) CVs
 *
 * Also a tag cloud aggregating TechnologyTag occurrences in candidate
 * projects (since the assignment says "Tag cloud with technology tags").
 *
 * All counts use single SQL aggregations — no loops.
 */
final readonly class StatisticsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<string, int> */
    public function globalStatistics(): array
    {
        $conn = $this->entityManager->getConnection();

        return [
            'cvsLast24h' => (int) $conn->fetchOne(
                "SELECT COUNT(id) FROM cv WHERE created_at >= NOW() - INTERVAL '24 hours'"
            ),
            'totalPositions' => (int) $conn->fetchOne('SELECT COUNT(id) FROM position'),
            'totalCandidates' => (int) $conn->fetchOne(
                "SELECT COUNT(id) FROM \"user\" WHERE roles::text LIKE '%ROLE_CANDIDATE%'"
            ),
            'totalRecruiters' => (int) $conn->fetchOne(
                "SELECT COUNT(id) FROM \"user\" WHERE roles::text LIKE '%ROLE_RECRUITER%'"
            ),
            'totalPublishedCvs' => (int) $conn->fetchOne(
                "SELECT COUNT(id) FROM cv WHERE status = 'PUBLISHED'"
            ),
        ];
    }

    /**
     * Returns [[name, count]] ordered by count desc, limited.
     *
     * @return list<array{name: string, count: int}>
     */
    public function tagCloud(int $limit = 30): array
    {
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare(
            'SELECT t.name AS name, COUNT(p.id) AS count
             FROM technology_tag t
             INNER JOIN project_technology_tag pt ON pt.technology_tag_id = t.id
             INNER JOIN project p ON p.id = pt.project_id
             GROUP BY t.name
             ORDER BY count DESC, t.name ASC
             LIMIT :lim'
        );
        $stmt->bindValue('lim', $limit, \Doctrine\DBAL\ParameterType::INTEGER);
        $rows = $stmt->executeQuery()->fetchAllAssociative();

        return array_map(
            static fn (array $r) => ['name' => (string) $r['name'], 'count' => (int) $r['count']],
            $rows
        );
    }
}