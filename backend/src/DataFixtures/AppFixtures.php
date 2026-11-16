<?php

namespace App\DataFixtures;

use App\Service\DemoSeeder;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    public function __construct(private readonly DemoSeeder $seeder)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->seeder->seed();
    }
}
