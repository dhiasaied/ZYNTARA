<?php

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

#[AsCommand(name: 'app:create-admin', description: 'Create an administrator, or promote an existing user to administrator.')]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::OPTIONAL, 'Required when the user does not exist yet')
            ->addArgument('name', InputArgument::OPTIONAL, 'Full name', 'Administrator');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower((string) $input->getArgument('email'));
        $user = $this->users->findOneBy(['email' => $email]);

        if (null === $user) {
            $password = (string) $input->getArgument('password');
            if (\strlen($password) < 8) {
                $io->error('Provide a password of at least 8 characters for a new user.');

                return Command::INVALID;
            }
            $user = (new User())->setEmail($email)->setFullName((string) $input->getArgument('name'));
            $user->setPassword($this->hasher->hashPassword($user, $password));
            $this->em->persist($user);
        }
        $user->setRoles(array_merge($user->getRoles(), ['ROLE_ADMIN']))->setActive(true);
        $this->em->flush();

        $io->success(\sprintf('%s is now an administrator.', $email));

        return Command::SUCCESS;
    }
}
