<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Psr\Container\ContainerInterface;

/** @var array{connection_params: array<string, mixed>, doctrine: array{paths: array<string>, is_dev_mode: bool}} $dbConfig */
$dbConfig = require __DIR__ . '/../database.php';

return [
    EntityManagerInterface::class => function (ContainerInterface $container) {
        /** @var EntityManager $em */
        $em = $container->get(EntityManager::class);
        return $em;
    },
    Connection::class => function () use ($dbConfig) {
        // @phpstan-ignore argument.type (config array from database.php)
        return DriverManager::getConnection($dbConfig['connection_params']);
    },
    EntityManager::class => function (ContainerInterface $container) use ($dbConfig) {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            $dbConfig['doctrine']['paths'],
            $dbConfig['doctrine']['is_dev_mode']
        );

        $config->setNamingStrategy(new UnderscoreNamingStrategy());

        /** @var Connection $conn */
        $conn = $container->get(Connection::class);
        return new EntityManager(
            $conn,
            $config
        );
    },
];
