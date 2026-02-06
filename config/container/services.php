<?php

use App\Entity\Packaging;
use App\Repository\PackerResponseCacheRepository;
use App\Services\InputValidator;
use App\Services\PackingService;
use Doctrine\ORM\EntityManager;
use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
    InputValidator::class => function () {
        return new InputValidator();
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
        );
    },
];
