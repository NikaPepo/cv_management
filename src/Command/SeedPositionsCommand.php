<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\CreatePositionDto;
use App\DTO\PositionAccessRuleInputDto;
use App\DTO\PositionAttributeInputDto;
use App\Entity\AttributeDefinition;
use App\Service\PositionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-positions',
    description: 'Seed a few demo positions so the UI has data to display.'
)]
final class SeedPositionsCommand extends Command
{
    public function __construct(
        private readonly PositionService $positionService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var AttributeDefinition[] $byName */
        $byName = [];
        foreach ($this->entityManager->getRepository(AttributeDefinition::class)->findAll() as $def) {
            $byName[$def->getName()] = $def;
        }

        $datasets = [
            [
                'title' => 'Junior Data Engineer @ Acme Corp.',
                'company' => 'Acme Corp.',
                'level' => 'junior',
                'attributes' => ['English Level', 'GPA', 'Remote Work', 'CAP'],
                'tagFilter' => ['python', 'sql', 'data'],
            ],
            [
                'title' => 'Senior Backend Engineer',
                'company' => 'Globex',
                'level' => 'senior',
                'attributes' => ['English Level', 'GPA', 'Remote Work', 'CAP', 'Presentation Skills'],
                'tagFilter' => ['php', 'go', 'sql'],
            ],
            [
                'title' => 'QA Engineer',
                'company' => 'Initech',
                'level' => 'middle',
                'attributes' => ['English Level'],
                'tagFilter' => [],
            ],
        ];

        foreach ($datasets as $row) {
            $attrs = [];
            $sort = 0;
            foreach ($row['attributes'] as $name) {
                if (!isset($byName[$name])) {
                    continue;
                }
                $attrs[] = new PositionAttributeInputDto(
                    attributeDefinitionId: $byName[$name]->getId(),
                    sortOrder: $sort++,
                );
            }

            $rules = [];
            if (isset($byName['CAP'])) {
                $rules[] = new PositionAccessRuleInputDto(
                    attributeDefinitionId: $byName['CAP']->getId(),
                    operator: 'ne',
                    value: 'None',
                );
            }

            $dto = new CreatePositionDto(
                title: $row['title'],
                shortDescription: 'Demo position auto-seeded for the UI.',
                company: $row['company'],
                level: $row['level'],
                isPublic: false,
                maxProjects: 4,
                projectTagFilter: $row['tagFilter'],
                attributes: $attrs,
                accessRules: $rules,
            );
            $this->positionService->create($dto);
            $io->writeln(sprintf('Created <info>%s</info>.', $row['title']));
        }

        $io->success('Positions seeded.');
        return Command::SUCCESS;
    }
}