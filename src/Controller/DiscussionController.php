<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreateDiscussionPostDto;
use App\Entity\DiscussionPost;
use App\Entity\Position;
use App\Entity\User;
use App\Repository\DiscussionPostRepository;
use App\Repository\PositionRepository;
use App\Service\DiscussionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/positions/{positionId}/discussion', requirements: ['positionId' => '\d+'])]
final class DiscussionController extends AbstractController
{
    public function __construct(
        private readonly DiscussionService $discussionService,
        private readonly DiscussionPostRepository $discussionRepository,
        private readonly PositionRepository $positionRepository,
    ) {
    }

    /**
     * Lists all posts for the position chronologically, or only posts
     * newer than `?sinceId=` for polling clients (the assignment asks
     * for updates within 2–5s).
     */
    #[Route('', methods: ['GET'])]
    public function list(int $positionId, Request $request): JsonResponse
    {
        $position = $this->positionRepository->find($positionId);
        if ($position === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $sinceId = $request->query->has('sinceId')
            ? max(0, $request->query->getInt('sinceId'))
            : null;

        $posts = $this->discussionRepository->findForPosition($position, $sinceId);

        return $this->json(array_map([self::class, 'present'], $posts));
    }

    #[Route('', methods: ['POST'])]
    public function post(
        int $positionId,
        #[MapRequestPayload] CreateDiscussionPostDto $dto,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $position = $this->positionRepository->find($positionId);
        if ($position === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $post = $this->discussionService->post($position, $user, $dto);
        return $this->json(self::present($post), Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    public static function present(DiscussionPost $post): array
    {
        $author = $post->getAuthor();
        $authorName = $author->getProfile()?->getFirstName()
            ?? explode('@', (string) $author->getEmail())[0];

        return [
            'id' => $post->getId(),
            'authorId' => $author->getId(),
            'authorName' => $authorName,
            'authorEmail' => $author->getEmail(),
            'content' => $post->getContent(),
            'createdAt' => $post->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}