<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Entity\Packaging;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PackagingFixture implements FixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $rows = [
            [2.5, 3.0, 1.0, 20.0],
            [4.0, 4.0, 4.0, 20.0],
            [2.0, 2.0, 10.0, 20.0],
            [5.5, 6.0, 7.5, 30.0],
            [9.0, 9.0, 9.0, 30.0],
        ];

        foreach ($rows as [$width, $height, $length, $maxWeight]) {
            $manager->persist(new Packaging($width, $height, $length, $maxWeight));
        }

        $manager->flush();
    }
}
