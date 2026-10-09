<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Position;
use App\Entity\PositionApiToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PositionApiToken>
 */
final class PositionApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PositionApiToken::class);
    }

    /**
     * Active token (not revoked) by its SHA-256 hash. Returns null for
     * missing OR revoked rows — callers should not distinguish the two.
     */
    public function findActiveByHash(string $tokenHash): ?PositionApiToken
    {
        return $this->createQueryBuilder('t')
            ->where('t.tokenHash = :hash')
            ->andWhere('t.revokedAt IS NULL')
            ->setParameter('hash', $tokenHash)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * All tokens for a Position, including revoked ones, newest first.
     *
     * @return PositionApiToken[]
     */
    public function findAllForPosition(Position $position): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.position = :position')
            ->setParameter('position', $position)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(PositionApiToken $token): void
    {
        $em = $this->getEntityManager();
        $em->persist($token);
        $em->flush();
    }

    /**
     * Hard-delete a token row. Used by `PositionApiTokenService::deleteRevoked()`
     * after the caller has confirmed the token is no longer active.
     */
    public function delete(PositionApiToken $token): void
    {
        $em = $this->getEntityManager();
        $em->remove($token);
        $em->flush();
    }
}
