<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Position;
use App\Enum\PositionLevel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Position>
 */
final class PositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Position::class);
    }

    /**
     * Positions that any authenticated user can browse (public ones only).
     * Used by anonymous browsing allowed by the assignment.
     *
     * @return Position[]
     */
    public function findPublic(int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isPublic = true')
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Positions accessible to a given profile: public ones + restricted
     * ones where the profile satisfies every access rule.
     *
     * Evaluation of access rules happens in PositionAccessEvaluator so
     * this method only does a cheap "public OR (restricted AND has access)"
     * fetch. The evaluator post-filters in PHP for the restricted case
     * because rules can mix dataTypes.
     *
     * @return Position[]
     */
    public function findAccessibleCandidates(int $limit = 100): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults($limit * 2) // over-fetch; access evaluator narrows down
            ->getQuery()
            ->getResult();
    }

    /**
     * Latest updated positions regardless of access. Used for the "Latest"
     * widget on the main page and the recruiter "all positions" view.
     *
     * @return Position[]
     */
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Top positions by number of submitted CVs. The count is done in SQL
     * (single GROUP BY) to avoid N+1. Note: published CVs only — DRAFT
     * CVs aren't visible to recruiters and shouldn't count.
     *
     * We can't GROUP BY the full Position in DQL because Postgres refuses
     * to compare JSON columns; a thin DBAL query is the cleanest fix.
     *
     * @return array<int, array{0: Position, cvs: int}>
     */
    public function findTopPopular(int $limit = 5): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare(
            'SELECT p.id, COUNT(c.id) AS cvs
             FROM position p
             LEFT JOIN cv c ON c.position_id = p.id AND c.status = :published
             GROUP BY p.id
             ORDER BY cvs DESC, p.updated_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue('published', 'PUBLISHED', \Doctrine\DBAL\ParameterType::STRING);
        $stmt->bindValue('lim', $limit, \Doctrine\DBAL\ParameterType::INTEGER);
        $rows = $stmt->executeQuery()->fetchAllAssociative();

        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn ($r) => (int) $r['id'], $rows);
        $positions = $this->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($positions as $p) {
            $byId[$p->getId()] = $p;
        }

        return array_map(static function ($r) use ($byId) {
            $id = (int) $r['id'];
            return [$byId[$id], (int) $r['cvs']];
        }, $rows);
    }

    /**
     * Filtered list for the recruiter view (table with sorting/filtering
     * by company + level). Always eager-loads attributes for the table row.
     *
     * @return Position[]
     */
    public function findFiltered(?string $company, ?PositionLevel $level, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('pa', 'def', 'opt')
            ->leftJoin('p.attributes', 'pa')
            ->leftJoin('pa.attributeDefinition', 'def')
            ->leftJoin('def.options', 'opt')
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults($limit);

        if ($company !== null && $company !== '') {
            $qb->andWhere('LOWER(p.company) LIKE :company')
                ->setParameter('company', '%' . mb_strtolower($company) . '%');
        }
        if ($level !== null) {
            $qb->andWhere('p.level = :level')
                ->setParameter('level', $level->value);
        }

        return $qb->getQuery()->getResult();
    }

    public function save(Position $position): void
    {
        $this->getEntityManager()->persist($position);
        $this->getEntityManager()->flush();
    }
}