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
    )
    {

    }

    /**
     * @param array $products
     * @return Packaging
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
        $requestHash = hash('sha256', $canonizedItemsString);
        $cached = $this->packerResponseCacheRepository->findByRequestHash($requestHash);
        if ($cached !== null) {
            $binData = json_decode($cached->responseBody, true);
        } else {
            $binData = $this->getFromApiAndCacheResponse($items, $requestHash);
        }

        if (!empty($binData)) {
            $packingFromDb = $this->packagingRepository->find($binData['id']);
            if ($packingFromDb === null) { // packaging no longer supported
                if ($cached !== null) { // clear cache
                    $this->entityManager->remove($cached);
                    $this->entityManager->flush();
                }

                $binData = $this->getFromApiAndCacheResponse($items, $requestHash); // load new packaging

                if (!empty($binData)) {
                    if (count($binData) > 1) {
                        throw new CannotFitInOneBinException();
                    }

                    $firstBin = $binData[0]; // always only one bin
                    $packingFromDb = $this->packagingRepository->find($firstBin['id']);
                }
            }
        }

        $packingFromDb = $packingFromDb ?? $this->determineOptimalPackagingLocally($this->getAvailablePackaging(), $items);

        if ($packingFromDb !== null) {
            return $packingFromDb;
        }

        throw new NoAppropriatePackagingFoundException();
    }

    /**
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

        if (empty($smallestSufficientBin)) {
            return null;
        }

        /** @var Packaging $packingFromDb */
        $packingFromDb = $this->packagingRepository->find($smallestSufficientBin['id']);

        return $packingFromDb;
    }

    /**
     * @throws NoPackagingInDatabaseException
     * @throws CannotFitInOneBinException
     */
    private function getFromApiAndCacheResponse($items, $requestHash): array
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

                $response = $body['response'];
                $binsPacked = $response['bins_packed'];
                $binData = $binsPacked[0]['bin_data'];

                $this->entityManager->persist(new PackerResponseCache($requestHash, json_encode($binData)));
                $this->entityManager->flush();

                return $binData;
            } else {
                $this->logger->error('Third party api error', [
                    'status_code' => $response->getStatusCode(),
                    'response_body' => (string) $response->getBody(),
                ]);
            }
        } catch (GuzzleException $e) {
            $this->logger->error('Packing service error', ['exception' => $e]);
        }

        return [];
    }

    /**
     * @return array<int, array{id: string, h: float, w: float, d: float, wg: string, max_wg: float, q: null, cost: int, type: string}>
     * @throws NoPackagingInDatabaseException
     */
    private function getAvailablePackaging(): array
    {
        $packings = $this->packagingRepository->findAll();
        if (empty($packings)) {
            $e = new NoPackagingInDatabaseException();
            $this->logger->error('No packaging found in database', ['exception' => $e]);
            throw $e;
        }

        return array_map(
            fn(Packaging $packaging) => $this->packagingToBin($packaging),
            $packings
        );
    }

    /**
     * @return array{id: string, h: float, w: float, d: float, wg: string, max_wg: float, q: null, cost: int, type: string}
     */
    private function packagingToBin(Packaging $packaging): array
    {
        return [
            'id' => $packaging->id,
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

    private function canonicalizeItems(array $items): array
    {
        ksort($items, SORT_STRING);

        foreach ($items as &$value) {
            if (is_array($value)) {
                ksort($value, SORT_STRING);
            }
        }

        return $items;
    }
}
