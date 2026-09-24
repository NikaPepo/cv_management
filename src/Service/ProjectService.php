<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateProjectDto;
use App\DTO\UpdateProjectDto;
use App\Entity\Profile;
use App\Entity\Project;
use App\Entity\TechnologyTag;
use App\Repository\ProjectRepository;
use App\Repository\TechnologyTagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly TechnologyTagRepository $tagRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function create(Profile $profile, CreateProjectDto $dto): Project
    {
        $project = new Project();
        $project->setProfile($profile);
        $this->apply($project, $dto->name, $dto->periodStart, $dto->periodEnd, $dto->markdownDescription);
        $this->syncTags($project, $dto->technologyTagNames);

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }

    public function update(Profile $profile, Project $project, UpdateProjectDto $dto): Project
    {
        $this->assertOwner($profile, $project);

        if ($dto->name !== null) {
            $project->setName($dto->name);
        }
        $this->apply(
            $project,
            $dto->name ?? $project->getName(),
            $dto->periodStart ?? null,
            $dto->periodEnd ?? null,
            $dto->markdownDescription ?? null
        );

        if ($dto->technologyTagNames !== null) {
            $this->syncTags($project, $dto->technologyTagNames);
        }

        $this->entityManager->flush();

        return $project;
    }

    public function delete(Profile $profile, Project $project): void
    {
        $this->assertOwner($profile, $project);
        $this->entityManager->remove($project);
        $this->entityManager->flush();
    }

    private function assertOwner(Profile $profile, Project $project): void
    {
        if ($project->getProfile()->getId() !== $profile->getId()) {
            throw new AccessDeniedHttpException('Project does not belong to current profile.');
        }
    }

    private function apply(
        Project $project,
        string $name,
        ?string $periodStart,
        ?string $periodEnd,
        ?string $markdown,
    ): void {
        $project->setName($name);

        $start = $this->parseDate($periodStart);
        $end = $this->parseDate($periodEnd);

        if ($start !== null && $end !== null && $end < $start) {
            throw new UnprocessableEntityHttpException('periodEnd must be on or after periodStart.');
        }

        $project->setPeriodStart($start);
        $project->setPeriodEnd($end);
        $project->setMarkdownDescription($markdown);
    }

    /**
     * Replaces the project's tag set, creating any new TechnologyTag rows on the fly.
     * Uses the unique index on `technology_tag.name` as the source of truth;
     * racing creates can race but the DB unique index catches duplicates.
     *
     * @param string[] $names
     */
    private function syncTags(Project $project, array $names): void
    {
        foreach ($project->getTechnologyTags() as $existing) {
            $project->removeTechnologyTag($existing);
        }

        foreach ($names as $raw) {
            $name = trim($raw);
            if ($name === '') {
                continue;
            }
            $tag = $this->tagRepository->findOneByName($name);
            if ($tag === null) {
                $tag = new TechnologyTag();
                $tag->setName($name);
                $this->entityManager->persist($tag);
            }
            $project->addTechnologyTag($tag);
        }
    }

    private function parseDate(?string $raw): ?\DateTimeImmutable
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if ($date === false) {
            throw new UnprocessableEntityHttpException('Invalid date; expected YYYY-MM-DD.');
        }
        return $date;
    }
}