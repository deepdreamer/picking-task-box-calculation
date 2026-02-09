<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Packaging;
use App\Entity\CachedPackaging;
use App\Services\Exception\ApiErrorException;
use App\Services\Exception\InvalidParameterException;
use App\Services\Exception\NoAppropriatePackagingFoundException;
use App\Services\Exception\NoPackagingInDatabaseException;
use App\Repository\PackagingRepository;
use App\Repository\CachedPackagingRepository;
use App\Services\Exception\UnexpectedApiResponseFormatException;
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
        private CachedPackagingRepository $cachedPackagingRepository,
        private EntityManagerInterface $entityManager,
        private LocalPackagingCalculator $localPackagingCalculator
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

        $items = self::canonicalizeItems(self::prepareProducts($products));
        $requestHash = self::buildRequestHash($products);
        $cached = $this->cachedPackagingRepository->findByRequestHash($requestHash);
        if ($cached !== null) {
            $binData = json_decode($cached->responseBody, true);
            $binData = is_array($binData) ? $binData : [];
        } else {
            try {
                $binData = $this->getFromApiAndCacheResponse($items, $requestHash, 'miss');
            } catch (UnexpectedApiResponseFormatException | ApiErrorException $e) {
                $this->logger->warning('API fail, using local fallback', [
                    ...$this->buildApiLogContext($items, $requestHash, 'miss'),
                    'exception' => $e,
                ]);

                $box = $this->determineOptimalPackagingLocally(
                    $this->getAvailablePackaging(),
                    $items
                );

                if ($box !== null) {
                    return $box;
                } else {
                    throw new NoAppropriatePackagingFoundException();
                }
            }
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

                try {
                    $binData = $this->getFromApiAndCacheResponse($items, $requestHash, 'stale_cache_refresh'); // load new packaging
                } catch (UnexpectedApiResponseFormatException | ApiErrorException $e) {
                    $this->logger->warning('API fail, using local fallback', [
                        ...$this->buildApiLogContext($items, $requestHash, 'stale_cache_refresh'),
                        'exception' => $e,
                    ]);

                    $box = $this->determineOptimalPackagingLocally(
                        $this->getAvailablePackaging(),
                        $items
                    );

                    if ($box !== null) {
                        return $box;
                    } else {
                        throw new NoAppropriatePackagingFoundException();
                    }
                }

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
     * @param list<array{width: int|float, height: int|float, length: int|float, weight: int|float}> $products
     */
    public static function buildRequestHash(array $products): string
    {
        $items = array_values(self::canonicalizeItems(self::prepareProducts($products)));
        return self::hashCanonicalizedItems($items);
    }

    /**
     * @param list<array{id: int|string, h: float, w: float, d: float, max_wg: float, q?: int, type?: string}> $availablePackaging
     * @param array<int, array{id: string, w: int|float, h: int|float, d: int|float, q: int, wg: int|float, vr?: int}> $items
     * @throws NoAppropriatePackagingFoundException|InvalidParameterException
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
     * @throws UnexpectedApiResponseFormatException|NoAppropriatePackagingFoundException
     * @throws ApiErrorException
     */
    private function getFromApiAndCacheResponse(array $items, string $requestHash, string $cacheContext): array
    {
        $endpoint = "$this->apiUrl/packer/findBinSize";

        try {
            $response = $this->client->post($endpoint, [
                'json' => [
                    'bins' => $this->getAvailablePackaging(),
                    'items' => $items,
                    'username' => $this->username,
                    'api_key' => $this->apiKey,
                    'params' => [
                        'optimization_mode' => 'bins_number',
                    ],
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $responseBodyString = $response->getBody()->getContents();
                $body = json_decode($responseBodyString, true);

                // Log response body in test or dev environments
                $env = $_ENV['APP_ENV'] ?? 'dev';
                if ($env === 'test' || $env === 'dev') {
                    $this->logger->debug('API response body', [
                        ...$this->buildApiLogContext($items, $requestHash, $cacheContext),
                        'endpoint' => $endpoint,
                        'response_body' => $responseBodyString,
                    ]);
                }

                if (!is_array($body)) {
                    throw new UnexpectedApiResponseFormatException('Response body is not a valid JSON object');
                }
                /** @var array<string, mixed>|null $responseData */
                $responseData = $body['response'] ?? [];
                $diagnostics = $this->extractApiDiagnostics($responseData);
                if ($diagnostics['api_errors'] !== []) {
                    $this->logger->warning('Third party api returned diagnostic fields', [
                        ...$this->buildApiLogContext($items, $requestHash, $cacheContext),
                        'endpoint' => $endpoint,
                        'api_status' => $diagnostics['api_status'],
                        'api_errors' => $diagnostics['api_errors'],
                    ]);

                    throw new ApiErrorException('Third party API returned error');
                }

                if (
                    !is_array($responseData)
                    || !isset($responseData['bins_packed'])
                    || !is_array($responseData['bins_packed'])
                ) {
                    throw new UnexpectedApiResponseFormatException('Missing or invalid response.bins_packed');
                }
                $binsPacked = $responseData['bins_packed'];
                $notPackedItems = $responseData['not_packed_items'] ?? [];
                if (!is_array($notPackedItems)) {
                    throw new UnexpectedApiResponseFormatException('response.not_packed_items is not an array');
                }

                // This service must return a single usable box only.
                if (count($binsPacked) !== 1 || $notPackedItems !== []) {
                    $this->logger->warning('API result cannot be represented as a single box.', [
                        ...$this->buildApiLogContext($items, $requestHash, $cacheContext),
                        'endpoint' => $endpoint,
                        'bins_packed_count' => count($binsPacked),
                        'not_packed_items_count' => count($notPackedItems),
                    ]);
                    throw new NoAppropriatePackagingFoundException();
                }

                if (
                    !isset($binsPacked[0])
                    || !is_array($binsPacked[0])
                    || !isset($binsPacked[0]['bin_data'])
                ) {
                    throw new UnexpectedApiResponseFormatException('Missing or invalid first bin_data in bins_packed');
                }
                $binData = $binsPacked[0]['bin_data'];
                if (!is_array($binData)) {
                    throw new UnexpectedApiResponseFormatException('bin_data is not an array');
                }
                /** @var array<string, mixed> $binData */
                $encoded = json_encode($binData);
                $this->entityManager->persist(
                    new CachedPackaging($requestHash, $encoded !== false ? $encoded : '')
                );
                $this->entityManager->flush();

                return $binData;
            }

            $responseBody = (string) $response->getBody();
            $decodedBody = json_decode($responseBody, true);
            $decodedResponse = is_array($decodedBody) && isset($decodedBody['response']) ? $decodedBody['response'] : null;
            /** @var array<string, mixed>|null $decodedResponse */
            $diagnostics = $this->extractApiDiagnostics($decodedResponse);
            $this->logger->error('Third party api error', [
                ...$this->buildApiLogContext($items, $requestHash, $cacheContext),
                'endpoint' => $endpoint,
                'status_code' => $response->getStatusCode(),
                'response_body' => $responseBody,
                'api_status' => $diagnostics['api_status'],
                'api_errors' => $diagnostics['api_errors'],
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Packing service error', [
                ...$this->buildApiLogContext($items, $requestHash, $cacheContext),
                'endpoint' => $endpoint,
                'exception' => $e,
            ]);
        }

        return [];
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
     * @param array<string, mixed>|null $responseData
     * @return array{api_status: int|string|null, api_errors: array<int|string, mixed>}
     */
    private function extractApiDiagnostics(?array $responseData = null): array
    {
        $status = null;
        $errors = [];

        if (is_array($responseData) && isset($responseData['status']) && (is_int($responseData['status']) || is_string($responseData['status']))) {
            $status = $responseData['status'];
        }
        if (is_array($responseData) && isset($responseData['errors']) && is_array($responseData['errors'])) {
            $errors = $responseData['errors'];
        }


        return [
            'api_status' => $status,
            'api_errors' => $errors,
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
     * @param list<array{width: int|float, height: int|float, length: int|float, weight: int|float}> $products
     * @return list<array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}>
     */
    private static function prepareProducts(array $products): array
    {
        return array_map(
            fn(array $product, int $index) => [
                'id' => 'Item' . ($index + 1),
                'w' => (int) ceil((float) $product['width']),
                'h' => (int) ceil((float) $product['height']),
                'd' => (int) ceil((float) $product['length']),
                'q' => 1,
                'wg' => (int) ceil((float) $product['weight']),
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
    private static function canonicalizeItems(array $items): array
    {
        usort(
            $items,
            static fn (array $a, array $b): int => [
                $a['w'],
                $a['h'],
                $a['d'],
                $a['wg'],
                $a['q'],
                $a['vr'],
            ] <=> [
                $b['w'],
                $b['h'],
                $b['d'],
                $b['wg'],
                $b['q'],
                $b['vr'],
            ]
        );

        foreach ($items as $index => &$item) {
            // Normalize identity so equivalent product sets hash to the same cache key.
            $item['id'] = 'Item' . ($index + 1);
        }
        unset($item);

        return $items;
    }

    /**
     * @param list<array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}> $items
     */
    private static function hashCanonicalizedItems(array $items): string
    {
        $canonizedItemsString = json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', $canonizedItemsString !== false ? $canonizedItemsString : '');
    }
}
