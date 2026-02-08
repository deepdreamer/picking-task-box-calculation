<?php

declare(strict_types=1);

use App\Entity\Packaging;
use App\Entity\CachedPackaging;
use App\Repository\PackagingRepository;
use App\Repository\CachedPackagingRepository;
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
    CachedPackagingRepository::class => function (ContainerInterface $container) {
        /** @var EntityManager $em */
        $em = $container->get(EntityManager::class);
        /** @var CachedPackagingRepository $repository */
        $repository = $em->getRepository(CachedPackaging::class);
        return $repository;
    },
];
