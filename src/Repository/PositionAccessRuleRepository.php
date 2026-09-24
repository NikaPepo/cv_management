<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Position;
use App\Entity\PositionAccessRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PositionAccessRule>
 */
final class PositionAccessRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PositionAccessRule::class);
    }

    /** @return PositionAccessRule[] */
    public function findForPosition(Position $position): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('def', 'opt')
            ->innerJoin('r.attributeDefinition', 'def')
            ->leftJoin('def.options', 'opt')
            ->where('r.position = :position')
            ->setParameter('position', $position)
            ->getQuery()
            ->getResult();
    }
}