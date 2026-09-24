<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AttributeDefinition;
use App\Entity\ProfileAttribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttributeDefinition>
 */
final class AttributeDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributeDefinition::class);
    }

    /**
     * Lookup by name prefix (case-insensitive). Used in the "lookup" autocomplete
     * component the assignment requires.
     *
     * @return AttributeDefinition[]
     */
    public function findByNamePrefix(string $prefix, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.name', 'ASC')
            ->setMaxResults($limit);

        if ($prefix !== '') {
            $qb->where('LOWER(a.name) LIKE :prefix')
                ->setParameter('prefix', mb_strtolower($prefix) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /** @return AttributeDefinition[] */
    public function findByCategory(int $categoryId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.category = :cid')
            ->setParameter('cid', $categoryId)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recently used attributes for a candidate, derived from their
     * existing ProfileAttribute rows. Used in the "recently used" autocomplete.
     *
     * @return AttributeDefinition[]
     */
    public function findRecentlyUsedByProfile(int $profileId, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->select('a')
            ->innerJoin(ProfileAttribute::class, 'pa', 'WITH', 'pa.attributeDefinition = a')
            ->where('pa.profile = :pid')
            ->setParameter('pid', $profileId)
            ->orderBy('pa.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneByName(string $name): ?AttributeDefinition
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function save(AttributeDefinition $attribute): void
    {
        $this->getEntityManager()->persist($attribute);
        $this->getEntityManager()->flush();
    }
}