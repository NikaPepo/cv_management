<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AttributeOption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttributeOption>
 */
final class AttributeOptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributeOption::class);
    }

    /** @return AttributeOption[] */
    public function findForAttribute(int $attributeDefinitionId): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.attributeDefinition = :aid')
            ->setParameter('aid', $attributeDefinitionId)
            ->orderBy('o.sortOrder', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}