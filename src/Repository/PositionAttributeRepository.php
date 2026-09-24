<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Position;
use App\Entity\PositionAttribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PositionAttribute>
 */
final class PositionAttributeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PositionAttribute::class);
    }

    /** @return PositionAttribute[] */
    public function findForPosition(Position $position): array
    {
        return $this->createQueryBuilder('pa')
            ->addSelect('def', 'opt')
            ->innerJoin('pa.attributeDefinition', 'def')
            ->leftJoin('def.options', 'opt')
            ->where('pa.position = :position')
            ->setParameter('position', $position)
            ->orderBy('pa.sortOrder', 'ASC')
            ->addOrderBy('pa.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}