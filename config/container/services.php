<?php

declare(strict_types=1);

use App\Entity\Packaging;
use App\Repository\PackagingRepository;
use App\Repository\CachedPackagingRepository;
use App\Services\InputValidator;
use App\Services\LocalPackagingCalculator;
use App\Services\OutputFormatter;
use App\Services\PackingApiClient;
use App\Services\PackingCache;
use App\Services\PackingService;
use App\Services\ProductNormalizer;
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
    ProductNormalizer::class => function () {
        return new ProductNormalizer();
    },
    OutputFormatter::class => function () {
        return new OutputFormatter();
    },
    PackingApiClient::class => function (ContainerInterface $container) {
        /** @var Client $client */
        $client = $container->get(Client::class);
        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);

        $apiUrl = $_ENV['API_URL'] ?? '';
        $apiKey = $_ENV['API_KEY'] ?? '';
        $apiUsername = $_ENV['API_USERNAME'] ?? '';
        $appEnv = $_ENV['APP_ENV'] ?? 'dev';

        $apiUrl = is_string($apiUrl) ? $apiUrl : '';
        $apiKey = is_string($apiKey) ? $apiKey : '';
        $apiUsername = is_string($apiUsername) ? $apiUsername : '';
        $appEnv = is_string($appEnv) ? $appEnv : 'dev';

        return new PackingApiClient(
            $apiUrl,
            $apiKey,
            $apiUsername,
            $appEnv,
            $client,
            $logger,
        );
    },
    PackingCache::class => function (ContainerInterface $container) {
        /** @var CachedPackagingRepository $cacheRepo */
        $cacheRepo = $container->get(CachedPackagingRepository::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        return new PackingCache($cacheRepo, $entityManager);
    },
    PackingService::class => function (ContainerInterface $container) {
        /** @var EntityManager $em */
        $em = $container->get(EntityManager::class);
        /** @var PackagingRepository $packagingRepo */
        $packagingRepo = $em->getRepository(Packaging::class);
        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var LocalPackagingCalculator $localCalc */
        $localCalc = $container->get(LocalPackagingCalculator::class);
        /** @var ProductNormalizer $productNormalizer */
        $productNormalizer = $container->get(ProductNormalizer::class);
        /** @var PackingApiClient $packingApiClient */
        $packingApiClient = $container->get(PackingApiClient::class);
        /** @var PackingCache $packingCache */
        $packingCache = $container->get(PackingCache::class);
        return new PackingService(
            $packagingRepo,
            $logger,
            $localCalc,
            $productNormalizer,
            $packingApiClient,
            $packingCache,
        );
    },
];
