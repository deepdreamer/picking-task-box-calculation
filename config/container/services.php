<?php

declare(strict_types=1);

use App\Entity\Packaging;
use App\Repository\PackagingRepository;
use App\Repository\CachedPackagingRepository;
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
        /** @var EntityManager $em */
        $em = $container->get(EntityManager::class);
        /** @var PackagingRepository $packagingRepo */
        $packagingRepo = $em->getRepository(Packaging::class);
        /** @var Client $client */
        $client = $container->get(Client::class);
        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var CachedPackagingRepository $cacheRepo */
        $cacheRepo = $container->get(CachedPackagingRepository::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        /** @var LocalPackagingCalculator $localCalc */
        $localCalc = $container->get(LocalPackagingCalculator::class);
        $apiUrl = $_ENV['API_URL'] ?? '';
        $apiKey = $_ENV['API_KEY'] ?? '';
        $apiUsername = $_ENV['API_USERNAME'] ?? '';
        $apiUrl = is_string($apiUrl) ? $apiUrl : '';
        $apiKey = is_string($apiKey) ? $apiKey : '';
        $apiUsername = is_string($apiUsername) ? $apiUsername : '';
        return new PackingService(
            $apiUrl,
            $apiKey,
            $apiUsername,
            $client,
            $packagingRepo,
            $logger,
            $cacheRepo,
            $entityManager,
            $localCalc,
        );
    },
];
