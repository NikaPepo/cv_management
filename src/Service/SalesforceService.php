<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\SalesforceExportInputDto;
use App\Entity\Profile;
use App\Entity\SalesforceConnection;
use App\Entity\User;
use App\Exception\Salesforce\SalesforceApiException;
use App\Exception\Salesforce\SalesforceStaleRecordException;
use App\Repository\SalesforceConnectionRepository;
use App\Service\Salesforce\SalesforceClient;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Orchestrates the "Export to Salesforce" workflow:
 *
 *   1. Resolve the Profile for the current User.
 *   2. If we already have a SalesforceConnection for this Profile:
 *        - PATCH the existing Account and Contact.
 *        - On 404 from Salesforce, fall back to creating new ones.
 *      Otherwise:
 *        - POST a new Account, then POST a Contact linked to it.
 *   3. Persist / update SalesforceConnection with the Salesforce IDs
 *      and clear any previous error message.
 *
 * Partial failure is handled by:
 *   - Saving the SalesforceAccountId as soon as Account creation
 *     succeeds, so a subsequent failure on Contact creation doesn't
 *     leave the integration in a state where the next Export creates
 *     a duplicate Account.
 *   - Recording lastError so a recruiter / admin can debug a recurring
 *     problem without re-running the export.
 *
 * The service never throws SalesforceStaleRecordException to its
 * caller — it transparently clears them and re-creates. Other
 * SalesforceException subclasses bubble up so the controller can
 * surface a 502.
 *
 * Not `final` so PHPUnit can mock it from controller tests.
 */
class SalesforceService
{
    public function __construct(
        private readonly SalesforceClient $client,
        private readonly SalesforceConnectionRepository $connections,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{accountId: string, contactId: ?string, created: bool}
     */
    public function exportForUser(User $user, SalesforceExportInputDto $dto): array
    {
        $profile = $user->getProfile();
        if ($profile === null) {
            throw new \RuntimeException('Cannot export to Salesforce: user has no profile.');
        }

        $existing = $this->connections->findForProfile($profile);

        // If the Profile doesn't carry a LastName, we fall back to the
        // value the user typed in the modal. If neither is set, we
        // bail out early — Salesforce rejects Contact creation without
        // a LastName and there is no point spending an API round-trip
        // on it. The frontend form is supposed to require it; this is
        // the defence-in-depth check.
        $lastName = $this->resolveLastName($profile, $dto);
        if ($lastName === null || $lastName === '') {
            throw new \InvalidArgumentException(
                'Last name is required to export to Salesforce (Salesforce Contact.LastName is mandatory).'
            );
        }

        $firstName = $profile->getFirstName();
        $email = $user->getEmail();
        $accountPayload = [
            'Name' => $dto->accountName,
        ];
        if ($dto->industry !== null && $dto->industry !== '') {
            $accountPayload['Industry'] = $dto->industry;
        }

        $contactPayload = [
            'LastName' => $lastName,
            'Description' => $dto->notes,
        ];
        if ($firstName !== null && $firstName !== '') {
            $contactPayload['FirstName'] = $firstName;
        }
        if ($email !== null && $email !== '') {
            $contactPayload['Email'] = $email;
        }
        // Title and Phone come from the optional form fields. We only
        // attach them when non-empty so an empty form doesn't write a
        // literal null into Salesforce and clobber a value the user
        // previously supplied via /ui (Salesforce accepts explicit null
        // for clearable fields, but we keep the contract one-way).
        if ($dto->title !== null && $dto->title !== '') {
            $contactPayload['Title'] = $dto->title;
        }
        if ($dto->phone !== null && $dto->phone !== '') {
            $contactPayload['Phone'] = $dto->phone;
        }

        try {
            if ($existing === null) {
                return $this->createFresh($profile, $accountPayload, $contactPayload);
            }

            return $this->updateExisting(
                $profile,
                $existing,
                $accountPayload,
                $contactPayload,
            );
        } catch (SalesforceStaleRecordException $e) {
            // Stale IDs: clear them and re-export from scratch. We
            // never throw this out — Salesforce told us the rows
            // are gone and the right thing to do is recreate.
            $this->connections->clearStaleIds($existing);
            return $this->createFresh($profile, $accountPayload, $contactPayload);
        }
    }

    /**
     * @return array{accountId: string, contactId: ?string, created: bool}
     */
    private function createFresh(
        Profile $profile,
        array $accountPayload,
        array $contactPayload,
    ): array {
        $account = $this->client->createAccount($accountPayload);

        // Persist the Account ID early so a Contact failure still
        // leaves us with a stable handle on the existing Account.
        $connection = new SalesforceConnection($profile, $account['id']);
        $this->entityManager->persist($connection);

        try {
            $contactPayload['AccountId'] = $account['id'];
            $contact = $this->client->createContact($contactPayload);
            $connection->setSalesforceContactId($contact['id']);
        } catch (SalesforceApiException $e) {
            // Account created, Contact failed. Persist what we have
            // so the next export PATCHes the existing Account
            // instead of POSTing a duplicate.
            $connection->setLastError($this->formatError($e));
            $this->entityManager->flush();
            throw $e;
        }

        $connection->setLastError(null);
        $connection->touchLastExportedAt();
        $this->entityManager->flush();

        return [
            'accountId' => $account['id'],
            'contactId' => $contact['id'],
            'created' => true,
        ];
    }

    /**
     * @return array{accountId: string, contactId: ?string, created: bool}
     */
    private function updateExisting(
        Profile $profile,
        SalesforceConnection $connection,
        array $accountPayload,
        array $contactPayload,
    ): array {
        $this->client->updateAccount($connection->getSalesforceAccountId(), $accountPayload);

        $contactId = $connection->getSalesforceContactId();
        if ($contactId === null) {
            // We have an Account but no Contact — that means an
            // earlier export crashed between the two POSTs. Recreate
            // the Contact rather than silently leaving it absent.
            $contactPayload['AccountId'] = $connection->getSalesforceAccountId();
            $contact = $this->client->createContact($contactPayload);
            $connection->setSalesforceContactId($contact['id']);
        } else {
            $this->client->updateContact($contactId, $contactPayload);
        }

        $connection->setLastError(null);
        $connection->touchLastExportedAt();
        $this->entityManager->flush();

        return [
            'accountId' => $connection->getSalesforceAccountId(),
            'contactId' => $connection->getSalesforceContactId(),
            'created' => false,
        ];
    }

    private function resolveLastName(Profile $profile, SalesforceExportInputDto $dto): ?string
    {
        $profileLastName = $profile->getLastName();
        if ($profileLastName !== null && $profileLastName !== '') {
            return $profileLastName;
        }
        if ($dto->lastName !== null && $dto->lastName !== '') {
            return $dto->lastName;
        }
        return null;
    }

    private function formatError(SalesforceApiException $e): string
    {
        $first = $e->salesforceErrors[0] ?? null;
        if (is_array($first)) {
            $message = $first['message'] ?? null;
            $code = $first['errorCode'] ?? null;
            if (is_string($message) && is_string($code)) {
                return sprintf('%s (%s)', $message, $code);
            }
            if (is_string($message)) {
                return $message;
            }
        }
        return sprintf('HTTP %d on %s', $e->statusCode, $e->endpoint);
    }
}