<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\Profile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cv>
 */
final class CvRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cv::class);
    }

    /**
     * Own CVs (any visibility) – used to back the "CVs" tab in the profile.
     * The CV list page itself decides visibility based on Position access.
     *
     * @return Cv[]
     */
    public function findForProfile(Profile $profile): array
    {
        return $this->createQueryBuilder('cv')
            ->addSelect('pos')
            ->innerJoin('cv.position', 'pos')
            ->where('cv.profile = :profile')
            ->setParameter('profile', $profile)
            ->orderBy('cv.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByProfileAndPosition(Profile $profile, Position $position): ?Cv
    {
        return $this->findOneBy([
            'profile' => $profile,
            'position' => $position,
        ]);
    }

    /**
     * Published CVs for a given Position. Used by recruiters to browse
     * candidates. We filter at the SQL level for PUBLISHED status; the
     * AccessEvaluator decides whether the recruiter is allowed to see
     * this position's CVs at all.
     *
     * @return Cv[]
     */
    public function findPublishedForPosition(Position $position): array
    {
        return $this->createQueryBuilder('cv')
            ->addSelect('prof', 'u')
            ->innerJoin('cv.profile', 'prof')
            ->innerJoin('prof.user', 'u')
            ->where('cv.position = :position')
            ->andWhere('cv.status = :published')
            ->setParameter('position', $position)
            ->setParameter('published', 'PUBLISHED')
            ->orderBy('cv.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(Cv $cv): void
    {
        $this->getEntityManager()->persist($cv);
        $this->getEntityManager()->flush();
    }
}