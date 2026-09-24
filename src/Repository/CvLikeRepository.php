<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Cv;
use App\Entity\User;
use App\Entity\CvLike;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CvLike>
 */
final class CvLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CvLike::class);
    }

    public function findOneByCvAndRecruiter(Cv $cv, User $recruiter): ?CvLike
    {
        return $this->findOneBy(['cv' => $cv, 'recruiter' => $recruiter]);
    }

    public function countForCv(Cv $cv): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.cv = :cv')
            ->setParameter('cv', $cv)
            ->getQuery()
            ->getSingleScalarResult();
    }
}