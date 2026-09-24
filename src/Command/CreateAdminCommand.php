<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Promote an existing user (by email) to ROLE_ADMIN, or create a new admin user.'
)]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'email',
                InputArgument::OPTIONAL,
                'Admin email. Falls back to ADMIN_BOOTSTRAP_EMAIL env var.',
            )
            ->addArgument(
                'password',
                InputArgument::OPTIONAL,
                'Admin password (only used for new users). Falls back to ADMIN_BOOTSTRAP_PASSWORD env var.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // CLI args take precedence; otherwise read from env. This lets
        // the same command work for both interactive shell use
        // (php bin/console app:create-admin user@x pw) and one-shot
        // container bootstrap on Render where the credentials come from
        // ADMIN_BOOTSTRAP_EMAIL / ADMIN_BOOTSTRAP_PASSWORD env vars.
        $email = $input->getArgument('email')
            ?? $_ENV['ADMIN_BOOTSTRAP_EMAIL'] ?? getenv('ADMIN_BOOTSTRAP_EMAIL') ?: null;
        $password = $input->getArgument('password')
            ?? $_ENV['ADMIN_BOOTSTRAP_PASSWORD'] ?? getenv('ADMIN_BOOTSTRAP_PASSWORD') ?: null;

        if ($email === null || $email === '' || $password === null || $password === '') {
            $io->error(
                'Both email and password are required — either as CLI arguments '
                . '(app:create-admin EMAIL PASSWORD) or via ADMIN_BOOTSTRAP_EMAIL / '
                . 'ADMIN_BOOTSTRAP_PASSWORD environment variables.'
            );
            return Command::FAILURE;
        }

        $email = (string) $email;
        $password = (string) $password;

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user === null) {
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setIsVerified(true);
            $this->entityManager->persist($user);
            $io->writeln(sprintf('Created user <info>%s</info>.', $email));
        }

        $user->setRoles(['ROLE_ADMIN']);
        $user->setIsVerified(true);
        $this->entityManager->flush();

        $io->success(sprintf('%s is now ROLE_ADMIN.', $email));
        return Command::SUCCESS;
    }
}