<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\CachedPackaging;
use App\Repository\CachedPackagingRepository;
use Doctrine\ORM\EntityManagerInterface;

class PackingCache
{
    public function __construct(
        private CachedPackagingRepository $cachedPackagingRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findByRequestHash(string $requestHash): ?CachedPackaging
    {
        return $this->cachedPackagingRepository->findByRequestHash($requestHash);
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeBinData(CachedPackaging $cachedPackaging): array
    {
        $decoded = json_decode($cachedPackaging->responseBody, true);
        if (!is_array($decoded) || array_is_list($decoded)) {
            /** @var array<string, mixed> $empty */
            $empty = [];
            return $empty;
        }

        if (!isset($decoded['id']) || (!is_int($decoded['id']) && !is_string($decoded['id']))) {
            /** @var array<string, mixed> $empty */
            $empty = [];
            return $empty;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $binData
     */
    public function save(string $requestHash, array $binData): void
    {
        $encoded = json_encode($binData);
        $this->entityManager->persist(new CachedPackaging($requestHash, $encoded !== false ? $encoded : ''));
        $this->entityManager->flush();
    }

    public function invalidate(CachedPackaging $cachedPackaging): void
    {
        $this->entityManager->remove($cachedPackaging);
        $this->entityManager->flush();
    }
}
