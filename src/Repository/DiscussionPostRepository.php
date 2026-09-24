<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DiscussionPost;
use App\Entity\Position;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DiscussionPost>
 */
final class DiscussionPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiscussionPost::class);
    }

    /**
     * Posts for a position, chronological. Append-only — never edited.
     *
     * @return DiscussionPost[]
     */
    public function findForPosition(Position $position, ?int $sinceId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('author')
            ->innerJoin('p.author', 'author')
            ->where('p.position = :position')
            ->setParameter('position', $position)
            ->orderBy('p.createdAt', 'ASC')
            ->addOrderBy('p.id', 'ASC');

        if ($sinceId !== null) {
            $qb->andWhere('p.id > :sinceId')
                ->setParameter('sinceId', $sinceId);
        }

        return $qb->getQuery()->getResult();
    }

    public function latestForPosition(Position $position): ?DiscussionPost
    {
        return $this->createQueryBuilder('p')
            ->where('p.position = :position')
            ->setParameter('position', $position)
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(DiscussionPost $post): void
    {
        $this->getEntityManager()->persist($post);
        $this->getEntityManager()->flush();
    }
}