<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Profile;
use App\Entity\SalesforceConnection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SalesforceConnection>
 *
 * Not `final` so PHPUnit can mock it from service-layer tests.
 */
class SalesforceConnectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalesforceConnection::class);
    }

    public function findForProfile(Profile $profile): ?SalesforceConnection
    {
        return $this->findOneBy(['profile' => $profile]);
    }

    /**
     * Salesforce told us (via 404 on PATCH) that the Account and/or
     * Contact it was pointing at have been deleted out-of-band. We
     * can't safely re-use those IDs, so we drop them — the next
     * Export will POST fresh records. Note we keep the SalesforceConnection
     * row itself (it still belongs to the Profile 1:1) and only null
     * the IDs that no longer resolve.
     */
    public function clearStaleIds(SalesforceConnection $connection): void
    {
        // The Profile 1:1 has its own unique index; the only way to
        // "forget" stale IDs without losing the row is to null both
        // columns and rely on the service to recreate. Since
        // salesforceAccountId is non-nullable on the entity, we
        // delete-and-recreate: easiest safe path. If a future
        // requirement is to keep the row, relax the column to
        // nullable and update this method to set both nulls.
        $em = $this->getEntityManager();
        $em->remove($connection);
        $em->flush();
    }
}