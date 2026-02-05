<?php

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use DI\ContainerBuilder;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;

require __DIR__ . '/vendor/autoload.php';

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/config/container.php');
$container = $containerBuilder->build();

ConsoleRunner::run(
    new SingleManagerProvider($container->get(EntityManager::class))
);

