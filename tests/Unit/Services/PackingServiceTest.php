<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\CachedPackaging;
use App\Entity\Packaging;
use App\Repository\CachedPackagingRepository;
use App\Repository\PackagingRepository;
use App\Services\Exception\NoAppropriatePackagingFoundException;
use App\Services\Exception\NoPackagingInDatabaseException;
use App\Services\LocalPackagingCalculator;
use App\Services\PackingService;
use Doctrine\ORM\EntityManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class PackingServiceTest extends TestCase
{
    public function testGetOptimalBoxReturnsCachedResultWhenCacheHit(): void
    {
        // Given
        $client = $this->createMock(Client::class);
        $packagingRepository = $this->createMock(PackagingRepository::class);
        $logger = $this->createStub(Logger::class);
        $cachedPackagingRepository = $this->createMock(CachedPackagingRepository::class);
        $entityManager = $this->createStub(EntityManager::class);
        $localPackagingCalculator = $this->createMock(LocalPackagingCalculator::class);

        $givenExpectedPackaging = new Packaging(10.0, 10.0, 10.0, 15.0);
        $givenCachedBinId = 'pack-1';
        $givenProducts = [
            ['width' => 2.0, 'height' => 2.0, 'length' => 2.0, 'weight' => 1.0],
            ['width' => 2.0, 'height' => 2.0, 'length' => 2.0, 'weight' => 1.0],
        ];
        $expectedRequestHash = $this->buildExpectedRequestHash($givenProducts);

        $this->whenPackagingIsCached($cachedPackagingRepository, $expectedRequestHash, $givenCachedBinId);
        $this->thenLocalCalculationWillNotBeUsed($localPackagingCalculator);
        $this->thenRequestToApiWillNotBeMade($client);
        $this->thenPackagingWillBeLoadedFromDb($packagingRepository, $givenCachedBinId, $givenExpectedPackaging);

        $packingService = new PackingService(
            'api-url-test',
            'api-key-test',
            'username-test',
            $client,
            $packagingRepository,
            $logger,
            $cachedPackagingRepository,
            $entityManager,
            $localPackagingCalculator,
        );

        // When
        $result = $packingService->getOptimalBox($givenProducts);

        // Then
        $this->assertSame($givenExpectedPackaging, $result);
    }

    public function testGetOptimalBoxCallsApiWhenCacheMiss(): void
    {
        // Given
        $client = $this->createMock(Client::class);
        $packagingRepository = $this->createMock(PackagingRepository::class);
        $logger = $this->createStub(Logger::class);
        $cachedPackagingRepository = $this->createMock(CachedPackagingRepository::class);
        $entityManager = $this->createStub(EntityManager::class);
        $localPackagingCalculator = $this->createMock(LocalPackagingCalculator::class);

        $givenExpectedPackaging = new Packaging(10.0, 10.0, 10.0, 15.0);
        $givenExpectedPackaging->id = 101;
        $givenBinId = (string) $givenExpectedPackaging->id;
        $givenProducts = [
            ['width' => 2.0, 'height' => 2.0, 'length' => 2.0, 'weight' => 1.0],
            ['width' => 2.0, 'height' => 2.0, 'length' => 2.0, 'weight' => 1.0],
        ];
        $expectedRequestHash = $this->buildExpectedRequestHash($givenProducts);
        $expectedItems = $this->buildExpectedApiItemsPayload($givenProducts);
        $expectedBins = $this->buildExpectedApiBinsPayload([$givenExpectedPackaging]);

        $this->whenPackagingIsNotCached($cachedPackagingRepository, $expectedRequestHash);
        $this->thenLocalCalculationWillNotBeUsed($localPackagingCalculator);
        $this->thenAvailablePackagingWillBeLoaded($packagingRepository, [$givenExpectedPackaging]);
        $this->thenApiWillBeCalled(
            $client,
            $expectedBins,
            $expectedItems,
            'username-test',
            'api-key-test',
            $givenBinId,
        );
        $this->thenPackagingWillBeLoadedFromDb($packagingRepository, $givenBinId, $givenExpectedPackaging);

        $packingService = new PackingService(
            'api-url-test',
            'api-key-test',
            'username-test',
            $client,
            $packagingRepository,
            $logger,
            $cachedPackagingRepository,
            $entityManager,
            $localPackagingCalculator,
        );

        // When
        $result = $packingService->getOptimalBox($givenProducts);

        // Then
        $this->assertSame($givenExpectedPackaging, $result);
    }

    public function testGetOptimalBoxRoundsUpProductMeasurementsBeforeApiCall(): void
    {
        // Given
        $client = $this->createMock(Client::class);
        $packagingRepository = $this->createMock(PackagingRepository::class);
        $logger = $this->createStub(Logger::class);
        $cachedPackagingRepository = $this->createMock(CachedPackagingRepository::class);
        $entityManager = $this->createStub(EntityManager::class);
        $localPackagingCalculator = $this->createMock(LocalPackagingCalculator::class);

        $givenExpectedPackaging = new Packaging(10.0, 10.0, 10.0, 15.0);
        $givenExpectedPackaging->id = 101;
        $givenBinId = (string) $givenExpectedPackaging->id;
        $givenProducts = [
            ['width' => 2.1, 'height' => 2.01, 'length' => 2.99, 'weight' => 1.01],
        ];
        $expectedRequestHash = $this->buildExpectedRequestHash($givenProducts);
        $expectedItems = $this->buildExpectedApiItemsPayload($givenProducts);
        $expectedBins = $this->buildExpectedApiBinsPayload([$givenExpectedPackaging]);

        $this->whenPackagingIsNotCached($cachedPackagingRepository, $expectedRequestHash);
        $this->thenLocalCalculationWillNotBeUsed($localPackagingCalculator);
        $this->thenAvailablePackagingWillBeLoaded($packagingRepository, [$givenExpectedPackaging]);
        $this->thenApiWillBeCalled(
            $client,
            $expectedBins,
            $expectedItems,
            'username-test',
            'api-key-test',
            $givenBinId,
        );
        $this->thenPackagingWillBeLoadedFromDb($packagingRepository, $givenBinId, $givenExpectedPackaging);

        $packingService = new PackingService(
            'api-url-test',
            'api-key-test',
            'username-test',
            $client,
            $packagingRepository,
            $logger,
            $cachedPackagingRepository,
            $entityManager,
            $localPackagingCalculator,
        );

        // When
        $result = $packingService->getOptimalBox($givenProducts);

        // Then
        $this->assertSame($givenExpectedPackaging, $result);
    }

    public function testGetOptimalBoxFallsBackToLocalWhenApiFails(): void
    {
        // Given
        $client = $this->createMock(Client::class);
        $packagingRepository = $this->createMock(PackagingRepository::class);
        $logger = $this->createStub(Logger::class);
        $cachedPackagingRepository = $this->createMock(CachedPackagingRepository::class);
        $entityManager = $this->createMock(EntityManager::class);
        $localPackagingCalculator = $this->createMock(LocalPackagingCalculator::class);

        $givenPackagingToBeReturned = new Packaging(10.0, 10.0, 10.0, 15.0);
        $givenPackagingToBeReturned->id = 101;
        $givenWrongPackagingExpectedNotToBeReturned = new Packaging(20.0, 20.0, 20.0, 30.0);
        $givenWrongPackagingExpectedNotToBeReturned->id = 202;
        $givenProducts = [
            ['width' => 2.0, 'height' => 2.0, 'length' => 2.0, 'weight' => 1.0],
            ['width' => 2.0, 'height' => 2.0, 'length' => 2.0, 'weight' => 1.0],
        ];
        $expectedRequestHash = $this->buildExpectedRequestHash($givenProducts);
        $expectedItems = $this->buildExpectedApiItemsPayload($givenProducts);
        $availablePackaging = [$givenPackagingToBeReturned, $givenWrongPackagingExpectedNotToBeReturned];
        $expectedBins = $this->buildExpectedApiBinsPayload($availablePackaging);

        $this->whenPackagingIsNotCached($cachedPackagingRepository, $expectedRequestHash);
        $this->thenAvailablePackagingWillBeLoadedFromDb($packagingRepository, $availablePackaging);
        $this->whenApiRequestFails($client);
        $this->thenLocalCalculationWillBeUsedAndReturnSelectedPackaging(
            $localPackagingCalculator,
            $expectedBins,
            $expectedItems,
            (string) $givenPackagingToBeReturned->id
        );
        $this->thenPackagingWillBeLoadedFromDb(
            $packagingRepository,
            (string) $givenPackagingToBeReturned->id,
            $givenPackagingToBeReturned
        );

        $this->thenNoWriteToDatabase($entityManager);

        $packingService = new PackingService(
            'api-url-test',
            'api-key-test',
            'username-test',
            $client,
            $packagingRepository,
            $logger,
            $cachedPackagingRepository,
            $entityManager,
            $localPackagingCalculator,
        );

        // When
        $result = $packingService->getOptimalBox($givenProducts);

        // Then
        $this->assertSame($givenPackagingToBeReturned, $result);
    }

    public function testGetOptimalBoxThrowsWhenNoPackagingInDatabase(): void
    {
        // Given
        $client = $this->createMock(Client::class);
        $packagingRepository = $this->createMock(PackagingRepository::class);
        $logger = $this->createStub(Logger::class);
        $cachedPackagingRepository = $this->createMock(CachedPackagingRepository::class);
        $entityManager = $this->createMock(EntityManager::class);
        $localPackagingCalculator = $this->createMock(LocalPackagingCalculator::class);

        $givenProducts = [
            ['width' => 2.0, 'height' => 2.0, 'length' => 2.0, 'weight' => 1.0],
        ];
        $expectedRequestHash = $this->buildExpectedRequestHash($givenProducts);

        $this->whenPackagingIsNotCached($cachedPackagingRepository, $expectedRequestHash);
        $this->whenNoAvailablePackagingInDb($packagingRepository);
        $this->thenLocalCalculationWillNotBeUsed($localPackagingCalculator);

        $client->expects($this->never())->method('post');

        $this->thenNoWriteToDatabase($entityManager);

        $packingService = new PackingService(
            'api-url-test',
            'api-key-test',
            'username-test',
            $client,
            $packagingRepository,
            $logger,
            $cachedPackagingRepository,
            $entityManager,
            $localPackagingCalculator,
        );

        // Then
        $this->expectException(NoPackagingInDatabaseException::class);

        // When
        $packingService->getOptimalBox($givenProducts);
    }

    #[DataProvider('singleBoxOnlyFitRequirementProvider')]
    public function testGetOptimalBoxThrowsWhenItemsDoNotFitAndApiIsUsed(array $givenProducts): void
    {
        // Given
        $client = $this->createMock(Client::class);
        $packagingRepository = $this->createMock(PackagingRepository::class);
        $logger = $this->createStub(Logger::class);
        $cachedPackagingRepository = $this->createMock(CachedPackagingRepository::class);
        $entityManager = $this->createMock(EntityManager::class);
        $localPackagingCalculator = $this->createMock(LocalPackagingCalculator::class);

        $availablePackaging = $this->buildAvailablePackagingForSingleBoxOnlyScenarios();

        $expectedRequestHash = $this->buildExpectedRequestHash($givenProducts);
        $expectedItems = $this->buildExpectedApiItemsPayload($givenProducts);
        $expectedBins = $this->buildExpectedApiBinsPayload($availablePackaging);

        $this->whenPackagingIsNotCached($cachedPackagingRepository, $expectedRequestHash);
        $this->thenAvailablePackagingWillBeLoaded($packagingRepository, $availablePackaging);
        $this->thenNoWriteToDatabase($entityManager);
        $this->thenApiWillBeCalledAndReturnsMultiplePackedBins(
            $client,
            $expectedBins,
            $expectedItems,
            'username-test',
            'api-key-test',
        );
        $this->thenLocalCalculationWillNotBeUsed($localPackagingCalculator);

        $packingService = new PackingService(
            'api-url-test',
            'api-key-test',
            'username-test',
            $client,
            $packagingRepository,
            $logger,
            $cachedPackagingRepository,
            $entityManager,
            $localPackagingCalculator,
        );

        // Then
        $this->expectException(NoAppropriatePackagingFoundException::class);

        // When
        $packingService->getOptimalBox($givenProducts);
    }

    #[DataProvider('singleBoxOnlyFitRequirementProvider')]
    public function testGetOptimalBoxThrowsWhenItemsDoNotFitAndLocalCalculationIsUsed(array $givenProducts): void
    {
        // Given
        $client = $this->createMock(Client::class);
        $packagingRepository = $this->createMock(PackagingRepository::class);
        $logger = $this->createStub(Logger::class);
        $cachedPackagingRepository = $this->createMock(CachedPackagingRepository::class);
        $entityManager = $this->createMock(EntityManager::class);
        $localPackagingCalculator = $this->createMock(LocalPackagingCalculator::class);

        $availablePackaging = $this->buildAvailablePackagingForSingleBoxOnlyScenarios();

        $expectedRequestHash = $this->buildExpectedRequestHash($givenProducts);
        $expectedItems = $this->buildExpectedApiItemsPayload($givenProducts);
        $expectedBins = $this->buildExpectedApiBinsPayload($availablePackaging);

        $this->whenPackagingIsNotCached($cachedPackagingRepository, $expectedRequestHash);
        $this->thenAvailablePackagingWillBeLoadedFromDb($packagingRepository, $availablePackaging);
        $this->whenApiRequestFails($client);
        $this->thenLocalCalculationWillBeUsedAndFail($localPackagingCalculator, $expectedBins, $expectedItems);
        $this->thenNoWriteToDatabase($entityManager);

        $packingService = new PackingService(
            'api-url-test',
            'api-key-test',
            'username-test',
            $client,
            $packagingRepository,
            $logger,
            $cachedPackagingRepository,
            $entityManager,
            $localPackagingCalculator,
        );

        // Then
        $this->expectException(NoAppropriatePackagingFoundException::class);

        // When
        $packingService->getOptimalBox($givenProducts);
    }

    /**
     * @return array<string, array{
     *     0: list<array{width: float, height: float, length: float, weight: float}>
     * }>
     */
    public static function singleBoxOnlyFitRequirementProvider(): array
    {
        return [
            'two heavy cubes exceed max weight in one box' => [[
                ['width' => 18.0, 'height' => 18.0, 'length' => 18.0, 'weight' => 60.0],
                ['width' => 18.0, 'height' => 18.0, 'length' => 18.0, 'weight' => 60.0],
            ]],
            'three medium cubes exceed max volume in one box' => [[
                ['width' => 16.0, 'height' => 16.0, 'length' => 16.0, 'weight' => 20.0],
                ['width' => 16.0, 'height' => 16.0, 'length' => 16.0, 'weight' => 20.0],
                ['width' => 16.0, 'height' => 16.0, 'length' => 16.0, 'weight' => 20.0],
            ]],
            'many small cubes exceed volume in one box' => [[
                ['width' => 10.0, 'height' => 10.0, 'length' => 10.0, 'weight' => 5.0],
                ['width' => 10.0, 'height' => 10.0, 'length' => 10.0, 'weight' => 5.0],
                ['width' => 10.0, 'height' => 10.0, 'length' => 10.0, 'weight' => 5.0],
                ['width' => 10.0, 'height' => 10.0, 'length' => 10.0, 'weight' => 5.0],
                ['width' => 10.0, 'height' => 10.0, 'length' => 10.0, 'weight' => 5.0],
                ['width' => 10.0, 'height' => 10.0, 'length' => 10.0, 'weight' => 5.0],
                ['width' => 10.0, 'height' => 10.0, 'length' => 10.0, 'weight' => 5.0],
                ['width' => 10.0, 'height' => 10.0, 'length' => 10.0, 'weight' => 5.0],
                ['width' => 10.0, 'height' => 10.0, 'length' => 10.0, 'weight' => 5.0],
                ['width' => 10.0, 'height' => 10.0, 'length' => 10.0, 'weight' => 5.0],
                ['width' => 10.0, 'height' => 10.0, 'length' => 10.0, 'weight' => 5.0],
            ]],
            'mixed items exceed both volume and weight in one box' => [[
                ['width' => 18.0, 'height' => 18.0, 'length' => 18.0, 'weight' => 50.0],
                ['width' => 18.0, 'height' => 18.0, 'length' => 18.0, 'weight' => 50.0],
                ['width' => 14.0, 'height' => 14.0, 'length' => 14.0, 'weight' => 20.0],
            ]],
        ];
    }

    public function testGetOptimalBoxInvalidatesCacheWhenCachedPackagingNoLongerInDb(): void
    {
        // Given
        $client = $this->createMock(Client::class);
        $packagingRepository = $this->createMock(PackagingRepository::class);
        $logger = $this->createStub(Logger::class);
        $cachedPackagingRepository = $this->createMock(CachedPackagingRepository::class);
        $entityManager = $this->createMock(EntityManager::class);
        $localPackagingCalculator = $this->createMock(LocalPackagingCalculator::class);

        $refreshedPackaging = new Packaging(10.0, 10.0, 10.0, 15.0);
        $refreshedPackaging->id = 101;
        $anotherAvailablePackaging = new Packaging(20.0, 20.0, 20.0, 30.0);
        $anotherAvailablePackaging->id = 202;

        $givenProducts = [
            ['width' => 2.0, 'height' => 2.0, 'length' => 2.0, 'weight' => 1.0],
            ['width' => 2.0, 'height' => 2.0, 'length' => 2.0, 'weight' => 1.0],
        ];
        $expectedRequestHash = $this->buildExpectedRequestHash($givenProducts);
        $expectedItems = $this->buildExpectedApiItemsPayload($givenProducts);
        $availablePackaging = [$refreshedPackaging, $anotherAvailablePackaging];
        $expectedBins = $this->buildExpectedApiBinsPayload($availablePackaging);
        $staleCachedBinId = '999';
        $refreshedBinId = (string) $refreshedPackaging->id;
        $cachedPackaging = $this->whenPackagingIsCached(
            $cachedPackagingRepository,
            $expectedRequestHash,
            $staleCachedBinId
        );

        $this->thenAvailablePackagingWillBeLoaded($packagingRepository, $availablePackaging);
        $this->thenApiWillBeCalled(
            $client,
            $expectedBins,
            $expectedItems,
            'username-test',
            'api-key-test',
            $refreshedBinId,
        );
        $this->thenLocalCalculationWillNotBeUsed($localPackagingCalculator);
        $this->thenPackagingLookupWillMissStaleThenReturnRefreshed(
            $packagingRepository,
            $staleCachedBinId,
            $refreshedBinId,
            $refreshedPackaging
        );
        $this->thenStaleCacheWillBeInvalidatedAndRefreshed(
            $entityManager,
            $cachedPackaging,
            $expectedRequestHash,
            $refreshedBinId
        );

        $packingService = new PackingService(
            'api-url-test',
            'api-key-test',
            'username-test',
            $client,
            $packagingRepository,
            $logger,
            $cachedPackagingRepository,
            $entityManager,
            $localPackagingCalculator,
        );

        // When
        $result = $packingService->getOptimalBox($givenProducts);

        // Then
        $this->assertSame($refreshedPackaging, $result);
    }

    #[DataProvider('requestHashConsistencyProvider')]
    public function testBuildRequestHashReturnsConsistentHashForEquivalentInput(
        array $givenProductsInFirstOrder,
        array $givenProductsInSecondOrder
    ): void {
        $given = $this->givenProductsForHashCalculation(
            $givenProductsInFirstOrder,
            $givenProductsInSecondOrder
        );
        $calculatedHashes = $this->whenRequestHashesAreCalculated($given);
        $this->thenRequestHashesAreEqual($calculatedHashes);
    }

    /**
     * @return array<string, array{
     *     0: list<array{width: float, height: float, length: float, weight: float}>,
     *     1: list<array{width: float, height: float, length: float, weight: float}>
     * }>
     */
    public static function requestHashConsistencyProvider(): array
    {
        return [
            'two products swapped' => [
                [
                    ['width' => 2.0, 'height' => 3.0, 'length' => 4.0, 'weight' => 1.0],
                    ['width' => 10.0, 'height' => 8.0, 'length' => 6.0, 'weight' => 5.0],
                ],
                [
                    ['width' => 10.0, 'height' => 8.0, 'length' => 6.0, 'weight' => 5.0],
                    ['width' => 2.0, 'height' => 3.0, 'length' => 4.0, 'weight' => 1.0],
                ],
            ],
            'three products shuffled' => [
                [
                    ['width' => 1.0, 'height' => 2.0, 'length' => 3.0, 'weight' => 4.0],
                    ['width' => 5.0, 'height' => 6.0, 'length' => 7.0, 'weight' => 8.0],
                    ['width' => 9.0, 'height' => 10.0, 'length' => 11.0, 'weight' => 12.0],
                ],
                [
                    ['width' => 9.0, 'height' => 10.0, 'length' => 11.0, 'weight' => 12.0],
                    ['width' => 1.0, 'height' => 2.0, 'length' => 3.0, 'weight' => 4.0],
                    ['width' => 5.0, 'height' => 6.0, 'length' => 7.0, 'weight' => 8.0],
                ],
            ],
        ];
    }

    private function whenPackagingIsCached(
        MockObject $cachedPackagingRepository,
        string $requestHash,
        string $cachedBinId
    ): CachedPackaging {
        $cachedPackaging = new CachedPackaging($requestHash, json_encode(['id' => $cachedBinId]) ?: '');
        $cachedPackagingRepository
            ->expects($this->once())
            ->method('findByRequestHash')
            ->with($requestHash)
            ->willReturn($cachedPackaging);

        return $cachedPackaging;
    }

    private function thenLocalCalculationWillNotBeUsed(MockObject $localPackagingCalculator): void
    {
        $localPackagingCalculator->expects($this->never())->method('calculateOptimalBin');
    }

    private function thenRequestToApiWillNotBeMade(MockObject $client): void
    {
        $client->expects($this->never())->method('post');
    }

    private function thenPackagingWillBeLoadedFromDb(
        MockObject $packagingRepository,
        string $binId,
        Packaging $givenExpectedPackaging
    ): void {
        $packagingRepository
            ->expects($this->once())
            ->method('find')
            ->with($binId)
            ->willReturn($givenExpectedPackaging);
    }

    /**
     * @param list<array{width: int|float, height: int|float, length: int|float, weight: int|float}> $products
     */
    private function buildExpectedRequestHash(array $products): string
    {
        return PackingService::buildRequestHash($products);
    }

    private function whenPackagingIsNotCached(
        MockObject $cachedPackagingRepository,
        string $expectedRequestHash
    ): void {
        $cachedPackagingRepository
            ->expects($this->once())
            ->method('findByRequestHash')
            ->with($expectedRequestHash)
            ->willReturn(null);
    }

    private function thenApiWillBeCalled(
        MockObject $client,
        array $expectedBins,
        array $expectedItems,
        string $expectedUsername,
        string $expectedApiKey,
        string $returnedBinId,
    ): void {
        $client
            ->expects($this->once())
            ->method('post')
            ->with($this->anything(), [
                'json' => [
                    'bins' => $expectedBins,
                    'items' => $expectedItems,
                    'username' => $expectedUsername,
                    'api_key' => $expectedApiKey,
                    'params' => [
                        'optimization_mode' => 'bins_number',
                    ],
                ],
            ])
            ->willReturn($this->mockApiResponse($returnedBinId));
    }

    private function thenApiWillBeCalledAndReturnsMultiplePackedBins(
        MockObject $client,
        array $expectedBins,
        array $expectedItems,
        string $expectedUsername,
        string $expectedApiKey,
    ): void {
        $client
            ->expects($this->once())
            ->method('post')
            ->with($this->anything(), [
                'json' => [
                    'bins' => $expectedBins,
                    'items' => $expectedItems,
                    'username' => $expectedUsername,
                    'api_key' => $expectedApiKey,
                    'params' => [
                        'optimization_mode' => 'bins_number',
                    ],
                ],
            ])
            ->willReturn($this->mockApiResponseWithMultiplePackedBins());
    }

    private function thenAvailablePackagingWillBeLoaded(
        MockObject $packagingRepository,
        array $availablePackagings
    ): void {
        $packagingRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($availablePackagings);
    }

    private function whenNoAvailablePackagingInDb(MockObject $packagingRepository): void
    {
        $packagingRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([]);
    }

    private function thenAvailablePackagingWillBeLoadedFromDb(
        MockObject $packagingRepository,
        array $availablePackagings
    ): void {
        $packagingRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($availablePackagings);
    }

    private function whenApiRequestFails(MockObject $client): void
    {
        $client
            ->expects($this->once())
            ->method('post')
            ->willThrowException(new class ('API request failed') extends \RuntimeException implements GuzzleException {
            });
    }

    private function thenLocalCalculationWillBeUsedAndReturnSelectedPackaging(
        MockObject $localPackagingCalculator,
        array $expectedBins,
        array $expectedItems,
        string $returnedPackagingId
    ): void {
        $localPackagingCalculator
            ->expects($this->once())
            ->method('calculateOptimalBin')
            ->with($expectedBins, $expectedItems)
            ->willReturn(['id' => $returnedPackagingId]);
    }

    private function thenLocalCalculationWillBeUsedAndFail(
        MockObject $localPackagingCalculator,
        array $expectedBins,
        array $expectedItems
    ): void {
        $localPackagingCalculator
            ->expects($this->once())
            ->method('calculateOptimalBin')
            ->with($expectedBins, $expectedItems)
            ->willReturn([]);
    }

    private function thenPackagingLookupWillMissStaleThenReturnRefreshed(
        MockObject $packagingRepository,
        string $staleCachedBinId,
        string $refreshedBinId,
        Packaging $refreshedPackaging
    ): void {
        $findCall = 0;
        $packagingRepository
            ->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(function (string $binId) use (&$findCall, $staleCachedBinId, $refreshedBinId, $refreshedPackaging): ?Packaging {
                $findCall++;
                if ($findCall === 1) {
                    $this->assertSame($staleCachedBinId, $binId);
                    return null;
                }

                $this->assertSame($refreshedBinId, $binId);
                return $refreshedPackaging;
            });
    }

    private function thenStaleCacheWillBeInvalidatedAndRefreshed(
        MockObject $entityManager,
        CachedPackaging $cachedPackaging,
        string $expectedRequestHash,
        string $refreshedBinId
    ): void {
        $entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($cachedPackaging);
        $entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (mixed $entity) use ($expectedRequestHash, $refreshedBinId): bool {
                if (!$entity instanceof CachedPackaging) {
                    return false;
                }

                $decoded = json_decode($entity->responseBody, true);
                return $entity->getRequestHash() === $expectedRequestHash
                    && is_array($decoded)
                    && ($decoded['id'] ?? null) === $refreshedBinId;
            }));
        $entityManager
            ->expects($this->exactly(2))
            ->method('flush');
    }

    private function mockApiResponse(string $returnedBinId): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $stream = $this->createStub(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);
        $stream->method('getContents')->willReturn((string) json_encode([
            'response' => [
                'bins_packed' => [
                    [
                        'bin_data' => ['id' => $returnedBinId],
                    ],
                ],
            ],
        ]));

        return $response;
    }

    private function mockApiResponseWithMultiplePackedBins(): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $stream = $this->createStub(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);
        $stream->method('getContents')->willReturn((string) json_encode([
            'response' => [
                'bins_packed' => [
                    [
                        'bin_data' => ['id' => '101'],
                    ],
                    [
                        'bin_data' => ['id' => '101'],
                    ],
                ],
                'not_packed_items' => [],
            ],
        ]));

        return $response;
    }

    /**
     * @return list<Packaging>
     */
    private function buildAvailablePackagingForSingleBoxOnlyScenarios(): array
    {
        $packagingA = new Packaging(20.0, 20.0, 20.0, 70.0);
        $packagingA->id = 101;
        $packagingB = new Packaging(22.0, 22.0, 22.0, 110.0);
        $packagingB->id = 202;

        return [$packagingA, $packagingB];
    }

    /**
     * @param list<Packaging> $packagings
     * @return list<array{id: string, h: float, w: float, d: float, max_wg: float, q: int, type: string}>
     */
    private function buildExpectedApiBinsPayload(array $packagings): array
    {
        return array_map(
            static fn (Packaging $packaging): array => [
                'id' => $packaging->id !== null ? (string) $packaging->id : '',
                'h' => $packaging->height,
                'w' => $packaging->width,
                'd' => $packaging->length,
                'max_wg' => $packaging->maxWeight,
                'q' => 1,
                'type' => 'box',
            ],
            $packagings
        );
    }

    /**
     * @param list<array{width: int|float, height: int|float, length: int|float, weight: int|float}> $products
     * @return list<array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}>
     */
    private function buildExpectedApiItemsPayload(array $products): array
    {
        $items = array_map(
            static fn (array $product): array => [
                'id' => '',
                'w' => (int) ceil((float) $product['width']),
                'h' => (int) ceil((float) $product['height']),
                'd' => (int) ceil((float) $product['length']),
                'q' => 1,
                'wg' => (int) ceil((float) $product['weight']),
                'vr' => 1,
            ],
            $products
        );

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
            $item['id'] = 'Item' . ($index + 1);
        }
        unset($item);

        return $items;
    }

    /**
     * @param array{
     *     products_in_first_order: list<array{width: float, height: float, length: float, weight: float}>,
     *     products_in_second_order: list<array{width: float, height: float, length: float, weight: float}>
     * } $context
     */
    private function givenProductsForHashCalculation(array $productsInFirstOrder, array $productsInSecondOrder): array
    {
        return [
            'products_in_first_order' => $productsInFirstOrder,
            'products_in_second_order' => $productsInSecondOrder,
        ];
    }

    /**
     * @param array{
     *     products_in_first_order: list<array{width: float, height: float, length: float, weight: float}>,
     *     products_in_second_order: list<array{width: float, height: float, length: float, weight: float}>
     * } $context
     * @return array{first_hash: string, second_hash: string}
     */
    private function whenRequestHashesAreCalculated(array $context): array
    {
        return [
            'first_hash' => PackingService::buildRequestHash($context['products_in_first_order']),
            'second_hash' => PackingService::buildRequestHash($context['products_in_second_order']),
        ];
    }

    /**
     * @param array{first_hash: string, second_hash: string} $result
     */
    private function thenRequestHashesAreEqual(array $result): void
    {
        $this->assertSame($result['first_hash'], $result['second_hash']);
    }

    private function thenNoWriteToDatabase(MockObject $entityManager): void
    {
        $entityManager
            ->expects($this->never())
            ->method('persist');
        $entityManager
            ->expects($this->never())
            ->method('flush');
    }
}
