<?php

namespace App\Tests\TestCase;

use DI\ContainerBuilder;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use App\Bootstrap\RouteLoader;

abstract class IntegrationTestCase extends TestCase
{
    protected function createApp(): App
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(__DIR__ . '/../../config/container.php');
        $container = $containerBuilder->build();

        AppFactory::setContainer($container);
        $app = AppFactory::create();
        RouteLoader::load($app);
        return $app;
    }
}
