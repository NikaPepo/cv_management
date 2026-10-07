<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\SalesforceExportInputDto;
use App\Entity\User;
use App\Exception\Salesforce\SalesforceException;
use App\Service\SalesforceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Thin entry point for the "Export to Salesforce" workflow.
 *
 *   POST /api/profile/salesforce
 *     { accountName, industry?, lastName?, notes? }
 *
 * All Salesforce business logic lives in SalesforceService. This
 * controller is intentionally narrow: validate DTO → resolve current
 * user → hand to service → render result. We never accept a userId /
 * profileId from the client because the export is always about the
 * *currently authenticated* user's own profile (Candidate, Recruiter
 * and Administrator all export their own contact card, never
 * someone else's).
 */
#[Route('/api/profile/salesforce')]
final class SalesforceController extends AbstractController
{
    public function __construct(
        private readonly SalesforceService $salesforceService,
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function export(
        #[MapRequestPayload] SalesforceExportInputDto $dto,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(
                ['error' => 'Authentication required.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }
        if ($user->getProfile() === null) {
            // Profile is created on registration, so this is a hard
            // error — bail out before we burn a Salesforce token.
            return $this->json(
                ['error' => 'Profile is not set up. Cannot export to Salesforce.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $result = $this->salesforceService->exportForUser($user, $dto);
        } catch (\InvalidArgumentException $e) {
            // Server-side validation that the DTO can't express
            // (LastName missing both in DB and on the form).
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (SalesforceException $e) {
            // Any Salesforce upstream problem — auth failure, API
            // 4xx/5xx, stale-id recovery that itself failed — becomes
            // a generic 502 with a non-leaking message. The detailed
            // error has already been recorded in
            // SalesforceConnection::lastError.
            return $this->json(
                [
                    'error' => 'Salesforce export failed. Please retry or contact support.',
                ],
                Response::HTTP_BAD_GATEWAY,
            );
        }

        return $this->json(
            [
                'success' => true,
                'accountId' => $result['accountId'],
                'contactId' => $result['contactId'],
                'created' => $result['created'],
            ],
            Response::HTTP_OK,
        );
    }
}