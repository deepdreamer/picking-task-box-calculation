<?php

use App\Entity\Packaging;
use App\Repository\PackerResponseCacheRepository;
use App\Services\InputValidator;
use App\Services\LocalPackagingCalculator;
use App\Services\OutputFormatter;
use App\Services\PackingService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
    InputValidator::class => function () {
        return new InputValidator();
    },
    LocalPackagingCalculator::class => function () {
        return new LocalPackagingCalculator();
    },
    OutputFormatter::class => function () {
        return new OutputFormatter();
    },
    PackingService::class => function (ContainerInterface $container) {
        return new PackingService(
            $_ENV['API_URL'],
            $_ENV['API_KEY'],
            $_ENV['API_USERNAME'],
            $container->get(Client::class),
            $container->get(EntityManager::class)->getRepository(Packaging::class),
            $container->get(LoggerInterface::class),
            $container->get(PackerResponseCacheRepository::class),
            $container->get(EntityManagerInterface::class),
            $container->get(LocalPackagingCalculator::class),
        );
    },
];
