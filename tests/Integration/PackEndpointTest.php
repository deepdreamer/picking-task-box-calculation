<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\CachedPackaging;
use App\Services\LocalPackagingCalculator;
use App\Services\ProductNormalizer;
use App\Tests\TestCase\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;

class PackEndpointTest extends IntegrationTestCase
{
    private const VALID_PRODUCTS = '{"products":[{"id":1,"width":1,"height":1,"length":1,"weight":1},{"id":2,"width":2,"height":2,"length":1,"weight":2},{"id":3,"width":1,"height":1,"length":1,"weight":1}]}';
    private const UNCACHED_VALID_PRODUCTS = '{"products":[{"id":999,"width":9,"height":9,"length":9,"weight":9}]}';
    private const UNCACHED_OVERSIZED_PRODUCTS = '{"products":[{"id":1000,"width":10,"height":10,"length":10,"weight":10}]}';

    public function testPackReturns200WithValidProducts(): void
    {
        $app = $this->givenAppWithMockedApiResponse();
        $response = $this->whenPackEndpointIsCalledWithValidProducts($app);
        $this->thenResponseContainsExpectedPackedBox($response);
    }

    public function testPackReturns4xxWithInvalidJson(): void
    {
        $app = $this->createApp();
        $response = $app->handle($this->givenRequestWithMalformedJson());
        $this->thenExpectException($response, 400, 'Products must be a JSON array.');
    }

    public function testPackReturns4xxWithEmptyProductArray(): void
    {
        $app = $this->createApp();
        $response = $app->handle($this->givenRequestWithEmptyProductArray());
        $this->thenExpectException($response, 400, 'Product list must not be empty.');
    }

    public function testPackReturns4xxWithMissingRequiredKeys(): void
    {
        $app = $this->createApp();
        $response = $app->handle($this->givenRequestWithMissingRequiredKeys());
        $this->thenExpectException($response, 400, "Product at index 0 is missing key 'weight'.");
    }

    public function testPackReturns422WhenItemsDoNotFit(): void
    {
        $app = $this->givenResponseFromApi(
            json_encode([
                'response' => [
                    'bins_packed' => [
                        ['bin_data' => ['id' => '1']],
                        ['bin_data' => ['id' => '2']],
                    ],
                    'not_packed_items' => [],
                ],
            ], JSON_THROW_ON_ERROR)
        );
        $response = $this->whenPackEndpointIsCalledWithProducts($app, self::UNCACHED_VALID_PRODUCTS);

        $this->thenExpectException($response, 422, 'No appropriate packaging found');
    }

    public function testPackReturns422WhenApiClientThrowsExceptionAndLocalFallbackCannotFit(): void
    {
        $app = $this->createApp([
            Client::class => static fn (): Client => new Client([
                'handler' => HandlerStack::create(new MockHandler([
                    new RequestException(
                        'API request failed',
                        new Request('POST', 'https://api.example.test/packer/packIntoMany')
                    ),
                ])),
            ]),
        ]);

        $response = $this->whenPackEndpointIsCalledWithProducts($app, self::UNCACHED_OVERSIZED_PRODUCTS);

        $this->thenExpectException($response, 422, 'No appropriate packaging found');
    }

    public function testPackResponseHasContentTypeApplicationJson(): void
    {
        $app = $this->givenAppWithMockedApiResponse();
        $response = $this->whenPackEndpointIsCalledWithValidProducts($app);

        $this->assertSame(['application/json'], $response->getHeader('Content-Type'));
    }

    public function testPackSameProductsTwiceReturnsSameResult(): void
    {
        $app = $this->givenAppWithMockedApiResponse();
        $firstResponse = $this->whenPackEndpointIsCalledWithValidProducts($app);
        $secondResponse = $this->whenPackEndpointIsCalledWithValidProducts($app);

        $this->assertSame(200, $firstResponse->getStatusCode());
        $this->assertSame(200, $secondResponse->getStatusCode());
        $this->assertJsonStringEqualsJsonString((string) $firstResponse->getBody(), (string) $secondResponse->getBody());
    }

    public function testPackCacheMissCreatesCachedPackagingRecordInDatabase(): void
    {
        $requestPayload = self::UNCACHED_VALID_PRODUCTS;
        /** @var Client&\PHPUnit\Framework\MockObject\MockObject $apiClient */
        $apiClient = $this->getMockBuilder(Client::class)
            ->onlyMethods(['post'])
            ->getMock();
        $apiClient
            ->expects($this->once())
            ->method('post')
            ->willReturn(new Response(
                200,
                ['Content-Type' => 'application/json'],
                (string) json_encode([
                    'response' => [
                        'bins_packed' => [
                            ['bin_data' => ['id' => '5']],
                        ],
                        'not_packed_items' => [],
                    ],
                ])
            ));

        $app = $this->createApp([
            Client::class => static fn () => $apiClient,
        ]);
        $requestHash = $this->buildRequestHashFromRequestPayload($app, $requestPayload);

        $response = $this->whenPackEndpointIsCalledWithProducts($app, $requestPayload);

        $this->assertSame(200, $response->getStatusCode());
        $this->thenCachedPackagingRowContainsBinId($app, $requestHash, '5');
    }

    public function testPackUsesCachedPackagingWithoutCallingApiOrLocalCalculator(): void
    {
        $requestPayload = self::VALID_PRODUCTS;
        $apiClient = $this->createMock(Client::class);
        $apiClient->expects($this->never())->method('post');
        $localPackagingCalculator = $this->createMock(LocalPackagingCalculator::class);
        $localPackagingCalculator->expects($this->never())->method('calculateOptimalBin');

        $app = $this->createApp([
            Client::class => static fn () => $apiClient,
            LocalPackagingCalculator::class => static fn () => $localPackagingCalculator,
        ]);
        $requestHash = $this->buildRequestHashFromRequestPayload($app, $requestPayload);
        $this->givenCachedPackagingRowInDatabase($app, $requestHash, '1');

        $response = $this->whenPackEndpointIsCalledWithProducts($app, $requestPayload);

        $this->thenResponseContainsExpectedPackedBox($response);
    }

    private function givenAppWithMockedApiResponse(): App
    {
        $apiResponseFixture = file_get_contents(__DIR__ . '/../ApiResponseExamples/expected_api_response.json');
        $this->assertNotFalse($apiResponseFixture, 'Fixture tests/ApiResponseExamples/expected_api_response.json must be readable.');

        return $this->givenResponseFromApi($apiResponseFixture);
    }

    private function givenResponseFromApi(string $responseBody): App
    {
        return $this->createApp([
            Client::class => static fn (): Client => new Client([
                'handler' => HandlerStack::create(new MockHandler([
                    new Response(200, ['Content-Type' => 'application/json'], $responseBody),
                ])),
            ]),
        ]);
    }

    private function whenPackEndpointIsCalledWithValidProducts(App $app): ResponseInterface
    {
        return $this->whenPackEndpointIsCalledWithProducts($app, self::VALID_PRODUCTS);
    }

    private function whenPackEndpointIsCalledWithProducts(App $app, string $products): ResponseInterface
    {
        $request = new ServerRequest(
            'POST',
            new Uri('http://localhost/pack'),
            ['Content-Type' => 'application/json'],
            $products
        );

        return $app->handle($request);
    }

    private function givenRequestWithMalformedJson(): ServerRequest
    {
        return new ServerRequest(
            'POST',
            new Uri('http://localhost/pack'),
            ['Content-Type' => 'application/json'],
            '{"products":[{"id":1,"width":1,"height":1,"length":1,"weight":1}]'
        );
    }

    private function givenRequestWithEmptyProductArray(): ServerRequest
    {
        return new ServerRequest(
            'POST',
            new Uri('http://localhost/pack'),
            ['Content-Type' => 'application/json'],
            '{"products":[]}'
        );
    }

    private function givenRequestWithMissingRequiredKeys(): ServerRequest
    {
        return new ServerRequest(
            'POST',
            new Uri('http://localhost/pack'),
            ['Content-Type' => 'application/json'],
            '{"products":[{"id":1,"width":1,"height":1,"length":1}]}'
        );
    }

    private function thenExpectException(ResponseInterface $response, int $statusCode, string $errorMessage): void
    {
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertSame(['application/json'], $response->getHeader('Content-Type'));
        $this->assertJsonStringEqualsJsonString(
            json_encode(['error' => $errorMessage], JSON_THROW_ON_ERROR),
            (string) $response->getBody()
        );
    }

    private function thenResponseContainsExpectedPackedBox(ResponseInterface $response): void
    {
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['application/json'], $response->getHeader('Content-Type'));
        $this->assertJsonStringEqualsJsonString(
            json_encode($this->expectedSuccessfulPackResponsePayload(), JSON_THROW_ON_ERROR),
            (string) $response->getBody()
        );
    }

    /**
     * @return array{
     *     success: bool,
     *     box: array{
     *         id: int,
     *         width: float,
     *         height: float,
     *         length: float,
     *         max_weight: float,
     *         dimensions: string
     *     }
     * }
     */
    private function expectedSuccessfulPackResponsePayload(): array
    {
        return [
            'success' => true,
            'box' => [
                'id' => 1,
                'width' => 2.5,
                'height' => 3.0,
                'length' => 1.0,
                'max_weight' => 20.0,
                'dimensions' => '2.50 × 3.00 × 1.00 cm',
            ],
        ];
    }

    private function buildRequestHashFromRequestPayload(App $app, string $payload): string
    {
        $container = $this->getContainerFromApp($app);
        /** @var ProductNormalizer $productNormalizer */
        $productNormalizer = $container->get(ProductNormalizer::class);

        /** @var array{products: list<array{width: int|float, height: int|float, length: int|float, weight: int|float}>} $decoded */
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        return $productNormalizer->buildRequestHash($decoded['products']);
    }

    private function givenCachedPackagingRowInDatabase(App $app, string $requestHash, string $binId): void
    {
        $container = $this->getContainerFromApp($app);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        $entityManager->persist(new CachedPackaging($requestHash, (string) json_encode(['id' => $binId])));
        $entityManager->flush();
    }

    private function thenCachedPackagingRowContainsBinId(App $app, string $requestHash, string $expectedBinId): void
    {
        $container = $this->getContainerFromApp($app);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        /** @var CachedPackaging|null $cachedPackaging */
        $cachedPackaging = $entityManager->find(CachedPackaging::class, $requestHash);
        $this->assertInstanceOf(
            CachedPackaging::class,
            $cachedPackaging,
            'Expected cached_packaging entity to exist for request hash.'
        );
        $this->assertSame($requestHash, $cachedPackaging->getRequestHash());

        $decodedResponseBody = json_decode($cachedPackaging->responseBody, true);
        $this->assertIsArray($decodedResponseBody);
        $this->assertSame($expectedBinId, (string) ($decodedResponseBody['id'] ?? ''));
    }

    private function getContainerFromApp(App $app): ContainerInterface
    {
        $container = $app->getContainer();
        $this->assertNotNull($container);

        return $container;
    }
}
