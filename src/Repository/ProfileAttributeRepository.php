<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AttributeDefinition;
use App\Entity\Profile;
use App\Entity\ProfileAttribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfileAttribute>
 */
final class ProfileAttributeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfileAttribute::class);
    }

    public function findOneByProfileAndDefinition(Profile $profile, AttributeDefinition $definition): ?ProfileAttribute
    {
        return $this->findOneBy([
            'profile' => $profile,
            'attributeDefinition' => $definition,
        ]);
    }

    /**
     * Returns all attribute rows for a profile, eager-loading the
     * AttributeDefinition (and its options) so the UI can render
     * without N+1 queries.
     *
     * @return ProfileAttribute[]
     */
    public function findAllForProfile(Profile $profile): array
    {
        return $this->createQueryBuilder('pa')
            ->addSelect('def', 'opt')
            ->innerJoin('pa.attributeDefinition', 'def')
            ->leftJoin('def.options', 'opt')
            ->where('pa.profile = :p')
            ->setParameter('p', $profile)
            ->orderBy('def.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(ProfileAttribute $attribute): void
    {
        $this->getEntityManager()->persist($attribute);
        $this->getEntityManager()->flush();
    }
}