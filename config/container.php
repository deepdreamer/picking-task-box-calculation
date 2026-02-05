<?php

use App\Controllers\PackController;
use App\Entity\Packaging;
use App\Repository\PackagingRepository;
use App\Services\InputValidator;
use App\Services\PackingService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;

$dbConfig = require __DIR__ . '/database.php';

return [
    PackagingRepository::class => function (ContainerInterface $container) {
        return $container->get(EntityManager::class)->getRepository(Packaging::class);
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
    // Guzzle HTTP Client
    Client::class => function () {
        return new Client();
    },
    InputValidator::class => function () {
        return new InputValidator();
    },
    PackController::class => function (ContainerInterface $container) {
        return new PackController(
            $container->get(PackingService::class),
            $container->get(InputValidator::class),
        );
    },
    PackingService::class => function (ContainerInterface $container) {
        return new PackingService(
            $_ENV['API_URL'],
            $_ENV['API_KEY'],
            $_ENV['API_USERNAME'],
            $container->get(Client::class),
            $container->get(EntityManager::class)->getRepository(Packaging::class)
        );
    }
];
