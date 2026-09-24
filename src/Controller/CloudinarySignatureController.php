<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CloudinarySignatureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/cloudinary')]
final class CloudinarySignatureController extends AbstractController
{
    public function __construct(
        private readonly CloudinarySignatureService $signatureService,
    ) {
    }

    /**
     * Returns a short-lived signed payload the browser can use to upload
     * a file directly to Cloudinary, bypassing the Symfony server.
     */
    #[Route('/signature', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function signature(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent() ?: '{}', true);
        $folder = is_array($payload) && isset($payload['folder']) && is_string($payload['folder'])
            ? $payload['folder']
            : 'cv-management';

        $folder = preg_replace('/[^a-z0-9_\-]/i', '', $folder) ?? 'cv-management';
        if ($folder === '') {
            $folder = 'cv-management';
        }

        return $this->json($this->signatureService->issueSignature($folder), Response::HTTP_OK);
    }
}