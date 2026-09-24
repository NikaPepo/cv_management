<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateDiscussionPostDto;
use App\Entity\DiscussionPost;
use App\Entity\Position;
use App\Entity\User;
use App\Repository\DiscussionPostRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DiscussionService
{
    public function __construct(
        private DiscussionPostRepository $discussionRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function post(Position $position, User $author, CreateDiscussionPostDto $dto): DiscussionPost
    {
        $post = new DiscussionPost();
        $post->setPosition($position);
        $post->setAuthor($author);
        $post->setContent($dto->content);

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return $post;
    }
}