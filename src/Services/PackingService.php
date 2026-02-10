<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Packaging;
use App\Repository\PackagingRepository;
use App\Services\Exception\ApiErrorException;
use App\Services\Exception\InvalidParameterException;
use App\Services\Exception\NoAppropriatePackagingFoundException;
use App\Services\Exception\NoPackagingInDatabaseException;
use App\Services\Exception\UnexpectedApiResponseFormatException;
use Psr\Log\LoggerInterface;

class PackingService
{
    public function __construct(
        private PackagingRepository $packagingRepository,
        private LoggerInterface $logger,
        private LocalPackagingCalculator $localPackagingCalculator,
        private ProductNormalizer $productNormalizer,
        private PackingApiClient $packingApiClient,
        private PackingCache $packingCache,
    ) {
    }

    /**
     * @param list<array{width: int|float, height: int|float, length: int|float, weight: int|float}> $products
     * @throws NoAppropriatePackagingFoundException
     * @throws NoPackagingInDatabaseException|InvalidParameterException
     */
    public function getOptimalBox(array $products): Packaging
    {
        if ($products === []) {
            throw new InvalidParameterException('at least one product is required');
        }

        $items = $this->productNormalizer->normalizeProducts($products);
        $requestHash = $this->productNormalizer->buildRequestHash($products);
        /** @var list<array{id: string, h: float, w: float, d: float, max_wg: float, q: int, type: string}>|null $availablePackaging */
        $availablePackaging = null;

        $cached = $this->packingCache->findByRequestHash($requestHash);
        $binData = $cached !== null ? $this->packingCache->decodeBinData($cached) : [];

        if ($cached === null) {
            $availablePackaging = $this->getAvailablePackaging();

            try {
                $binData = $this->getFromApiAndCacheResponse($items, $requestHash, 'miss', $availablePackaging);
            } catch (UnexpectedApiResponseFormatException | ApiErrorException $e) {
                return $this->resolveLocalFallbackOrThrow($availablePackaging, $items, $requestHash, 'miss', $e);
            }
        }

        if ($binData !== []) {
            $packingFromDb = $this->findPackagingByBinData($binData);
            if ($packingFromDb !== null) {
                return $packingFromDb;
            }

            if ($cached !== null) {
                $this->packingCache->invalidate($cached);
                $availablePackaging = $this->getAvailablePackaging();

                try {
                    $binData = $this->getFromApiAndCacheResponse(
                        $items,
                        $requestHash,
                        'stale_cache_refresh',
                        $availablePackaging
                    );
                } catch (UnexpectedApiResponseFormatException | ApiErrorException $e) {
                    return $this->resolveLocalFallbackOrThrow(
                        $availablePackaging,
                        $items,
                        $requestHash,
                        'stale_cache_refresh',
                        $e,
                    );
                }

                if ($binData !== []) {
                    $packingFromDb = $this->findPackagingByBinData($binData);
                    if ($packingFromDb !== null) {
                        return $packingFromDb;
                    }
                }
            }
        }

        if ($availablePackaging === null) {
            $availablePackaging = $this->getAvailablePackaging();
        }

        $packingFromDb = $this->determineOptimalPackagingLocally($availablePackaging, $items);
        if ($packingFromDb !== null) {
            return $packingFromDb;
        }

        throw new NoAppropriatePackagingFoundException();
    }

    /**
     * @param list<array{id: int|string, h: float, w: float, d: float, max_wg: float, q?: int, type?: string}> $availablePackaging
     * @param array<int, array{id: string, w: int|float, h: int|float, d: int|float, q: int, wg: int|float, vr?: int}> $items
     */
    private function determineOptimalPackagingLocally(array $availablePackaging, array $items): ?Packaging
    {
        $smallestSufficientBin = $this->localPackagingCalculator->calculateOptimalBin(
            $availablePackaging,
            $items
        );

        if ($smallestSufficientBin === []) {
            return null;
        }

        /** @var array{id: int|string} $smallestSufficientBin */
        /** @var Packaging|null $packingFromDb */
        $packingFromDb = $this->packagingRepository->find($smallestSufficientBin['id']);

        return $packingFromDb;
    }

    /**
     * @param array<int, array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}> $items
     * @param list<array{id: string, h: float, w: float, d: float, max_wg: float, q: int, type: string}> $availablePackaging
     * @return array<string, mixed>
     * @throws UnexpectedApiResponseFormatException
     * @throws NoAppropriatePackagingFoundException
     * @throws ApiErrorException
     */
    private function getFromApiAndCacheResponse(array $items, string $requestHash, string $cacheContext, array $availablePackaging): array
    {
        $binData = $this->packingApiClient->findSingleBinData($availablePackaging, $items, $requestHash, $cacheContext);
        if ($binData !== []) {
            $this->packingCache->save($requestHash, $binData);
        }

        return $binData;
    }

    /**
     * @param list<array{id: string, h: float, w: float, d: float, max_wg: float, q: int, type: string}> $availablePackaging
     * @param array<int, array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}> $items
     * @throws NoAppropriatePackagingFoundException
     */
    private function resolveLocalFallbackOrThrow(
        array $availablePackaging,
        array $items,
        string $requestHash,
        string $cacheContext,
        \Throwable $exception,
    ): Packaging {
        $this->logger->warning('API fail, using local fallback', [
            ...$this->buildApiLogContext($items, $requestHash, $cacheContext),
            'exception' => $exception,
        ]);

        $box = $this->determineOptimalPackagingLocally($availablePackaging, $items);
        if ($box !== null) {
            return $box;
        }

        throw new NoAppropriatePackagingFoundException();
    }

    /**
     * @param array<int, array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}> $items
     * @return array{request_hash: string, items_count: int, cache_context: string}
     */
    private function buildApiLogContext(array $items, string $requestHash, string $cacheContext): array
    {
        return [
            'request_hash' => $requestHash,
            'items_count' => count($items),
            'cache_context' => $cacheContext,
        ];
    }

    /**
     * @return list<array{id: string, h: float, w: float, d: float, max_wg: float, q: int, type: string}>
     * @throws NoPackagingInDatabaseException
     */
    private function getAvailablePackaging(): array
    {
        $packings = $this->packagingRepository->findAll();
        if ($packings === []) {
            $e = new NoPackagingInDatabaseException();
            $this->logger->error('No packaging found in database', ['exception' => $e]);
            throw $e;
        }

        return array_map(
            fn (Packaging $packaging) => $this->packagingToBin($packaging),
            $packings
        );
    }

    /**
     * @return array{id: string, h: float, w: float, d: float, max_wg: float, q: int, type: string}
     */
    private function packagingToBin(Packaging $packaging): array
    {
        return [
            'id' => $packaging->id !== null ? (string) $packaging->id : '',
            'h' => $packaging->height,
            'w' => $packaging->width,
            'd' => $packaging->length,
            'max_wg' => $packaging->maxWeight,
            'q' => 1,
            'type' => 'box',
        ];
    }

    /**
     * @param array<string, mixed> $binData
     */
    private function findPackagingByBinData(array $binData): ?Packaging
    {
        if (!isset($binData['id']) || (!is_string($binData['id']) && !is_int($binData['id']))) {
            return null;
        }

        /** @var Packaging|null $packaging */
        $packaging = $this->packagingRepository->find($binData['id']);

        return $packaging;
    }
}
