<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Profile;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
final class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Returns projects of a profile with technology tags eager-loaded so
     * the UI does not trigger N+1.
     *
     * @return Project[]
     */
    public function findForProfile(Profile $profile): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('t')
            ->leftJoin('p.technologyTags', 't')
            ->where('p.profile = :profile')
            ->setParameter('profile', $profile)
            ->orderBy('p.periodStart', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns up to $limit projects of a profile whose tags intersect the given
     * set, ordered by periodStart DESC. Used by CV generation to pick the
     * relevant projects for a Position.
     *
     * @param string[] $tagNames
     * @return Project[]
     */
    public function findForProfileFilteredByTags(Profile $profile, array $tagNames, int $limit): array
    {
        if ($tagNames === []) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->addSelect('t')
            ->innerJoin('p.technologyTags', 't')
            ->where('p.profile = :profile')
            ->andWhere('LOWER(t.name) IN (:tags)')
            ->setParameter('profile', $profile)
            ->setParameter('tags', array_map('mb_strtolower', $tagNames))
            ->orderBy('p.periodStart', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(Project $project): void
    {
        $this->getEntityManager()->persist($project);
        $this->getEntityManager()->flush();
    }
}