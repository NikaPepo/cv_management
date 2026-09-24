<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Cv;
use App\Entity\Position;
use App\Repository\CvRepository;
use App\Repository\PositionRepository;
use App\Service\CvViewAssembler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recruiter/Admin-only: list CVs for a given position.
 * Used by the "Browse CVs for this position" page the assignment requires.
 */
final class PositionCvController extends AbstractController
{
    public function __construct(
        private readonly CvRepository $cvRepository,
        private readonly PositionRepository $positionRepository,
        private readonly CvViewAssembler $cvViewAssembler,
    ) {
    }

    #[Route('/api/positions/{id}/cvs', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function listForPosition(int $id): JsonResponse
    {
        if (!$this->isGranted('ROLE_RECRUITER') && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Recruiter role required.'], Response::HTTP_FORBIDDEN);
        }

        $position = $this->positionRepository->find($id);
        if ($position === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $rows = array_map(
            fn (Cv $cv) => $this->cvViewAssembler->assemble($cv),
            $this->cvRepository->findPublishedForPosition($position)
        );

        return $this->json([
            'position' => [
                'id' => $position->getId(),
                'title' => $position->getTitle(),
                'company' => $position->getCompany(),
            ],
            'cvs' => $rows,
        ]);
    }
}