<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\DemoSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Loads the demo workspace on an empty database. Works in prod images (no dev dependencies). */
#[AsCommand(name: 'app:seed-demo', description: 'Create the demo users, agents, crew and knowledge base if the database is empty.')]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly DemoSeeder $seeder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->users->count([]) > 0) {
            $io->note('Database already has users: demo data not loaded.');

            return Command::SUCCESS;
        }
        $this->seeder->seed();
        $io->success(\sprintf('Demo workspace created. Sign in as %s / Demo#2026 (admin: %s / Admin#2026).', DemoSeeder::DEMO_EMAIL, DemoSeeder::ADMIN_EMAIL));

        return Command::SUCCESS;
    }
}
