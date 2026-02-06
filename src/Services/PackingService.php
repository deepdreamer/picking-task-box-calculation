<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Packaging;
use App\Entity\PackerResponseCache;
use App\Services\Exception\CannotFitInOneBinException;
use App\Services\Exception\NoAppropriatePackagingFoundException;
use App\Services\Exception\NonPositiveItemVolumeException;
use App\Services\Exception\NonPositiveItemWeightException;
use App\Services\Exception\NoPackagingInDatabaseException;
use App\Repository\PackagingRepository;
use App\Repository\PackerResponseCacheRepository;
use App\Services\Exception\TotalItemsDimensionsException;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class PackingService
{
    public function __construct(
        private string $apiUrl,
        private string $apiKey,
        private string $username,
        private Client $client,
        private PackagingRepository $packagingRepository,
        private LoggerInterface $logger,
        private PackerResponseCacheRepository $packerResponseCacheRepository,
        private EntityManagerInterface $entityManager,
        private LocalPackagingCalculator $localPackagingCalculator
    ) {
    }

    /**
     * @param list<array{width: int, height: int, length: int, weight: int}> $products
     * @throws CannotFitInOneBinException
     * @throws NoAppropriatePackagingFoundException
     * @throws NoPackagingInDatabaseException
     * @throws NonPositiveItemVolumeException
     * @throws NonPositiveItemWeightException
     * @throws TotalItemsDimensionsException
     */
    public function getOptimalBox(array $products): Packaging
    {
        $items = $this->canonicalizeItems($this->prepareProducts($products));
        $canonizedItemsString = json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $requestHash = hash('sha256', $canonizedItemsString !== false ? $canonizedItemsString : '');
        $cached = $this->packerResponseCacheRepository->findByRequestHash($requestHash);
        if ($cached !== null) {
            $binData = json_decode($cached->responseBody, true);
            $binData = is_array($binData) ? $binData : [];
        } else {
            $binData = $this->getFromApiAndCacheResponse($items, $requestHash);
        }

        $packingFromDb = null;
        if ($binData !== []) {
            /** @var array{id: int|string} $binData */
            $packingFromDb = $this->packagingRepository->find($binData['id']);
            if ($packingFromDb === null) { // packaging no longer supported
                if ($cached !== null) { // clear cache
                    $this->entityManager->remove($cached);
                    $this->entityManager->flush();
                }

                $binData = $this->getFromApiAndCacheResponse($items, $requestHash); // load new packaging

                if ($binData !== []) {
                    /** @var array{id: int|string} $binData */
                    $packingFromDb = $this->packagingRepository->find($binData['id']);
                }
            }
        }

        $packingFromDb = $packingFromDb ?? $this->determineOptimalPackagingLocally(
            $this->getAvailablePackaging(),
            $items
        );

        if ($packingFromDb !== null) {
            return $packingFromDb;
        }

        throw new NoAppropriatePackagingFoundException();
    }

    /**
     * @param list<array{id: int|string, h: float, w: float, d: float, max_wg: float, type?: string}> $availablePackaging
     * @param array<int, array{id: string, w: int|float, h: int|float, d: int|float, q: int, wg: int|float, vr?: int}> $items
     * @throws TotalItemsDimensionsException
     * @throws CannotFitInOneBinException
     * @throws NonPositiveItemWeightException
     * @throws NonPositiveItemVolumeException
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
     * @return array<string, mixed>
     * @throws NoPackagingInDatabaseException
     * @throws CannotFitInOneBinException
     */
    private function getFromApiAndCacheResponse(array $items, string $requestHash): array
    {
        try {
            $response = $this->client->post("$this->apiUrl/packer/pack", [
                'json' => [
                    'bins' => $this->getAvailablePackaging(),
                    'items' => $items,
                    'username' => $this->username,
                    'api_key' => $this->apiKey,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $body = json_decode($response->getBody()->getContents(), true);
                if (!is_array($body)) {
                    return [];
                }
                $responseData = $body['response'] ?? [];
                if (
                    !is_array($responseData)
                    || !isset($responseData['bins_packed'])
                    || !is_array($responseData['bins_packed'])
                ) {
                    return [];
                }
                $binsPacked = $responseData['bins_packed'];
                if (
                    !isset($binsPacked[0])
                    || !is_array($binsPacked[0])
                    || !isset($binsPacked[0]['bin_data'])
                ) {
                    return [];
                }
                $binData = $binsPacked[0]['bin_data'];
                if (!is_array($binData)) {
                    return [];
                }
                /** @var array<string, mixed> $binData */
                $encoded = json_encode($binData);
                $this->entityManager->persist(
                    new PackerResponseCache($requestHash, $encoded !== false ? $encoded : '')
                );
                $this->entityManager->flush();

                return $binData;
            }

            $this->logger->error('Third party api error', [
                'status_code' => $response->getStatusCode(),
                'response_body' => (string) $response->getBody(),
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Packing service error', ['exception' => $e]);
        }

        return [];
    }

    /**
     * @return list<array{id: string, h: float, w: float, d: float, max_wg: float, type: string}>
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
     * @return array{id: string, h: float, w: float, d: float, max_wg: float, type: string}
     */
    private function packagingToBin(Packaging $packaging): array
    {
        return [
            'id' => $packaging->id !== null ? (string) $packaging->id : '',
            'h' => $packaging->height,
            'w' => $packaging->width,
            'd' => $packaging->length,
            'max_wg' => $packaging->maxWeight,
            'type' => 'box',
        ];
    }

    /**
     * @param list<array{width: int, height: int, length: int, weight: int}> $products
     * @return list<array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}>
     */
    private function prepareProducts(array $products): array
    {
        return array_map(
            fn(array $product, int $index) => [
                'id' => 'Item' . ($index + 1),
                'w' => $product['width'],
                'h' => $product['height'],
                'd' => $product['length'],
                'q' => 1,
                'wg' => $product['weight'],
                'vr' => 1,
            ],
            $products,
            array_keys($products)
        );
    }

    /**
     * @param list<array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}> $items
     * @return array<int, array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}>
     */
    private function canonicalizeItems(array $items): array
    {
        ksort($items, SORT_STRING);

        foreach ($items as &$value) {
            ksort($value, SORT_STRING);
        }

        return $items;
    }
}
