<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\PositionController;
use App\Entity\Position;
use App\Entity\User;
use App\Repository\PositionRepository;
use App\Service\PositionAccessEvaluator;
use App\Service\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Aggregated payload for the React HomePage: latest positions, popular
 * positions (with submittedCvs count), tag cloud, and global stats.
 *
 * Access policy mirrors /api/positions:
 *
 *   - Anonymous and Candidate callers see only positions they can
 *     actually access. PositionAccessEvaluator post-filters the
 *     over-fetched set so the user gets a real top-N / latest-N of
 *     ACCESSIBLE positions, never a global top-N with restricted rows
 *     silently dropped.
 *   - Recruiter and Administrator callers see the full pool of
 *     positions, including restricted ones, because the position pool
 *     is shared (no per-recruiter ownership) and staff need to manage
 *     positions regardless of who set the access rules. This matches
 *     PositionController::list()'s ?all=1 path.
 *
 * Staff detection reuses the same predicate there: isGranted('ROLE_RECRUITER').
 * The ROLE_ADMIN → ROLE_RECRUITER hierarchy in security.yaml means admin
 * accounts match too.
 */
final class MainPageController extends AbstractController
{
    /** How many positions the "Latest" widget displays. */
    private const LATEST_LIMIT = 10;

    /**
     * Over-fetch factor for "Latest" used when access-filtering is
     * applied. PositionAccessEvaluator runs in PHP (rules can mix
     * dataTypes), so we ask the repository for extra candidates to make
     * sure we can still fill the widget after access filtering when the
     * top positions happen to be restricted.
     */
    private const LATEST_OVERFETCH = 50;

    /** How many positions the "Most popular" widget shows. */
    private const POPULAR_LIMIT = 5;

    /**
     * Over-fetch factor for "Most popular" used when access-filtering is
     * applied. findTopPopular already runs the CV GROUP BY server-side;
     * we over-fetch a larger pool because popularity ordering is
     * independent of access, so we may need to scan further down the
     * ranking to find 5 accessible positions.
     */
    private const POPULAR_OVERFETCH = 50;

    public function __construct(
        private readonly PositionRepository $positionRepository,
        private readonly PositionAccessEvaluator $accessEvaluator,
        private readonly StatisticsService $statisticsService,
    ) {
    }

    #[Route('/api/main-page', methods: ['GET'])]
    public function main(#[CurrentUser] ?User $user): JsonResponse
    {
        // ROLE_ADMIN inherits ROLE_RECRUITER via security.yaml's role
        // hierarchy, so isGranted('ROLE_RECRUITER') matches both staff
        // kinds. Same predicate PositionController::list uses.
        if ($this->isGranted('ROLE_RECRUITER')) {
            $latest = array_map(
                static fn (Position $p) => PositionController::present($p),
                $this->positionRepository->findLatest(self::LATEST_LIMIT)
            );

            $popularRows = $this->positionRepository->findTopPopular(self::POPULAR_LIMIT);
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

        // Anonymous + Candidate: same identity-based policy as
        // PositionController::list. Anonymous users see public positions
        // only; candidates see public + restricted positions they
        // qualify for.
        $profile = $user?->getProfile();

        $latestAccessible = array_values(array_filter(
            $this->positionRepository->findLatest(self::LATEST_OVERFETCH),
            fn (Position $p) => $this->accessEvaluator->isAccessible($p, $profile),
        ));
        $latest = array_map(
            static fn (Position $p) => PositionController::present($p),
            array_slice($latestAccessible, 0, self::LATEST_LIMIT),
        );

        // Popular: same approach, but findTopPopular returns
        // [Position, cvs_count] tuples. Survivors keep their popularity
        // order after the filter, so the slice still yields a real
        // top-N among positions the user can actually access.
        $popularAccessible = array_values(array_filter(
            $this->positionRepository->findTopPopular(self::POPULAR_OVERFETCH),
            fn (array $row) => $this->accessEvaluator->isAccessible($row[0], $profile),
        ));
        $popular = array_map(static function (array $row) {
            $position = $row[0];
            return PositionController::present($position) + ['submittedCvs' => (int) $row[1]];
        }, array_slice($popularAccessible, 0, self::POPULAR_LIMIT));

        return $this->json([
            'latest' => $latest,
            'popular' => $popular,
            'tagCloud' => $this->statisticsService->tagCloud(30),
            'statistics' => $this->statisticsService->globalStatistics(),
        ]);
    }
}