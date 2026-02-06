<?php

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Psr\Container\ContainerInterface;

$dbConfig = require __DIR__ . '/../database.php';

return [
    EntityManagerInterface::class => function (ContainerInterface $container) {
        return $container->get(EntityManager::class);
    },
    Connection::class => function () use ($dbConfig) {
        return DriverManager::getConnection($dbConfig['connection_params']);
    },
    EntityManager::class => function (ContainerInterface $container) use ($dbConfig) {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            $dbConfig['doctrine']['paths'],
            $dbConfig['doctrine']['is_dev_mode']
        );

        $config->setNamingStrategy(new UnderscoreNamingStrategy());

        return new EntityManager(
            $container->get(Connection::class),
            $config
        );
    },
];
