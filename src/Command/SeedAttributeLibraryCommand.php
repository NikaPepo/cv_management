<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AttributeCategory;
use App\Entity\AttributeDefinition;
use App\Entity\AttributeOption;
use App\Enum\AttributeDataType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Drops a small set of AttributeCategories and AttributeDefinitions into the DB
 * so the UI has something to display. Safe to run repeatedly: it skips rows
 * that already exist by code / name.
 */
#[AsCommand(
    name: 'app:seed-attribute-library',
    description: 'Seed the attribute library with a few demo categories and definitions.'
)]
final class SeedAttributeLibraryCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $em = $this->entityManager;

        $categories = [
            ['code' => 'certification', 'name' => 'Certification', 'sortOrder' => 10],
            ['code' => 'domain_knowledge', 'name' => 'Domain Knowledge', 'sortOrder' => 20],
            ['code' => 'personal', 'name' => 'Personal Information', 'sortOrder' => 30],
            ['code' => 'soft_skills', 'name' => 'Soft Skills', 'sortOrder' => 40],
        ];

        $persisted = [];
        foreach ($categories as $row) {
            $existing = $em->getRepository(AttributeCategory::class)->findOneBy(['code' => $row['code']]);
            if ($existing !== null) {
                $persisted[$row['code']] = $existing;
                continue;
            }
            $cat = new AttributeCategory();
            $cat->setCode($row['code']);
            $cat->setName($row['name']);
            $cat->setSortOrder($row['sortOrder']);
            $em->persist($cat);
            $persisted[$row['code']] = $cat;
        }

        $em->flush();

        $definitions = [
            [
                'name' => 'IELTS Score',
                'description' => 'Overall IELTS band (0-9).',
                'dataType' => AttributeDataType::Numeric,
                'category' => 'certification',
                'options' => [],
            ],
            [
                'name' => 'CAP',
                'description' => 'Certified Analytics Professional level.',
                'dataType' => AttributeDataType::OneOfMany,
                'category' => 'certification',
                'options' => ['None', 'Essentials', 'Pro', 'Expert'],
            ],
            [
                'name' => 'English Level',
                'description' => 'Self-reported English proficiency.',
                'dataType' => AttributeDataType::OneOfMany,
                'category' => 'soft_skills',
                'options' => ['Beginner', 'Intermediate', 'Advanced', 'Native'],
            ],
            [
                'name' => 'Remote Work',
                'description' => 'Open to remote engagements.',
                'dataType' => AttributeDataType::Boolean,
                'category' => 'personal',
                'options' => [],
            ],
            [
                'name' => 'Presentation Skills',
                'description' => 'Comfort presenting to stakeholders.',
                'dataType' => AttributeDataType::OneOfMany,
                'category' => 'soft_skills',
                'options' => ['Basic', 'Confident', 'Advanced'],
            ],
            [
                'name' => 'Date of Birth',
                'description' => 'Date of birth.',
                'dataType' => AttributeDataType::Date,
                'category' => 'personal',
                'options' => [],
            ],
            [
                'name' => 'Bio',
                'description' => 'Short Markdown biography.',
                'dataType' => AttributeDataType::Text,
                'category' => 'personal',
                'options' => [],
            ],
        ];

        foreach ($definitions as $row) {
            $exists = $em->getRepository(AttributeDefinition::class)->findOneBy(['name' => $row['name']]);
            if ($exists !== null) {
                continue;
            }
            $def = new AttributeDefinition();
            $def->setName($row['name']);
            $def->setDescription($row['description']);
            $def->setDataType($row['dataType']);
            $def->setCategory($persisted[$row['category']]);
            $def->setRequired(false);

            $sort = 0;
            foreach ($row['options'] as $value) {
                $opt = new AttributeOption();
                $opt->setAttributeDefinition($def);
                $opt->setValue($value);
                $opt->setSortOrder($sort++);
                $def->getOptions()->add($opt);
            }

            $em->persist($def);
        }

        $em->flush();

        $io->success('Attribute library seeded.');
        return Command::SUCCESS;
    }
}