<?php

declare(strict_types=1);

use App\Entity\Packaging;
use App\Entity\PackerResponseCache;
use App\Repository\PackagingRepository;
use App\Repository\PackerResponseCacheRepository;
use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;

return [
    PackagingRepository::class => function (ContainerInterface $container) {
        return $container->get(EntityManager::class)->getRepository(Packaging::class);
    },
    PackerResponseCacheRepository::class => function (ContainerInterface $container) {
        return $container->get(EntityManager::class)->getRepository(PackerResponseCache::class);
    },
];
