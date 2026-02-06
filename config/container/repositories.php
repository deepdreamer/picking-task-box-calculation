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
        /** @var EntityManager $em */
        $em = $container->get(EntityManager::class);
        /** @var PackagingRepository $repository */
        $repository = $em->getRepository(Packaging::class);
        return $repository;
    },
    PackerResponseCacheRepository::class => function (ContainerInterface $container) {
        /** @var EntityManager $em */
        $em = $container->get(EntityManager::class);
        /** @var PackerResponseCacheRepository $repository */
        $repository = $em->getRepository(PackerResponseCache::class);
        return $repository;
    },
];
