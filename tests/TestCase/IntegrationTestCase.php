<?php

declare(strict_types=1);

namespace App\Tests\TestCase;

use App\Bootstrap\RouteLoader;
use App\Tests\Fixtures\PackagingFixture;
use DI\ContainerBuilder;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;

abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();
    }

    /**
     * @param array<string, mixed> $definitions
     * @throws \Exception
     */
    protected function createApp(array $definitions = []): App
    {
        $container = $this->createContainer($definitions);

        AppFactory::setContainer($container);
        $app = AppFactory::create();
        RouteLoader::load($app);
        return $app;
    }

    /**
     * @param array<string, mixed> $definitions
     * @throws \Exception
     */
    private function createContainer(array $definitions = []): ContainerInterface
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(__DIR__ . '/../../config/container.php');
        if ($definitions !== []) {
            $containerBuilder->addDefinitions($definitions);
        }

        return $containerBuilder->build();
    }

    private function refreshDatabase(): void
    {
        $container = $this->createContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \RuntimeException('EntityManagerInterface not available in test container');
        }

        try {
            $purger = new ORMPurger($entityManager);
            $purger->purge();
            $entityManager->getConnection()->executeStatement('ALTER TABLE packaging AUTO_INCREMENT = 1');

            $executor = new ORMExecutor($entityManager, $purger);
            $executor->execute([
                new PackagingFixture(),
            ], true);
        } finally {
            $entityManager->clear();
            $entityManager->getConnection()->close();
        }

    }
}
