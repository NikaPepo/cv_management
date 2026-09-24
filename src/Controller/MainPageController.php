<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\PositionController;
use App\Repository\PositionRepository;
use App\Service\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MainPageController extends AbstractController
{
    public function __construct(
        private readonly PositionRepository $positionRepository,
        private readonly StatisticsService $statisticsService,
    ) {
    }

    #[Route('/api/main-page', methods: ['GET'])]
    public function main(): JsonResponse
    {
        $latest = array_map(
            static fn ($p) => PositionController::present($p),
            $this->positionRepository->findLatest(10)
        );

        $popularRows = $this->positionRepository->findTopPopular(5);
        $popular = array_map(static function (array $row) {
            $position = $row[0];
            return PositionController::present($position) + ['submittedCvs' => (int) $row[1]];
        }, $popularRows);

        return $this->json([
            'latest' => $latest,
            'popular' => $popular,
            'tagCloud' => $this->statisticsService->tagCloud(30),
            'statistics' => $this->statisticsService->globalStatistics(),
        ]);
    }
}