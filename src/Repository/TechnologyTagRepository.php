<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TechnologyTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TechnologyTag>
 */
final class TechnologyTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TechnologyTag::class);
    }

    /**
     * Prefix autocomplete used by the tag input. Case-insensitive on the
     * stored canonical name.
     *
     * @return TechnologyTag[]
     */
    public function findByPrefix(string $prefix, int $limit = 10): array
    {
        $needle = mb_strtolower($prefix);

        return $this->createQueryBuilder('t')
            ->where('t.name LIKE :needle')
            ->setParameter('needle', $needle . '%')
            ->orderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneByName(string $name): ?TechnologyTag
    {
        return $this->findOneBy(['name' => mb_strtolower(trim($name))]);
    }

    public function save(TechnologyTag $tag): void
    {
        $this->getEntityManager()->persist($tag);
        $this->getEntityManager()->flush();
    }
}