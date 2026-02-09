<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\TestCase\IntegrationTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;

class PackEndpointTest extends IntegrationTestCase
{
    private const VALID_PRODUCTS = '{"products":[{"id":1,"width":1,"height":1,"length":1,"weight":1},{"id":2,"width":2,"height":2,"length":1,"weight":2},{"id":3,"width":1,"height":1,"length":1,"weight":1}]}';
    private const UNCACHED_VALID_PRODUCTS = '{"products":[{"id":999,"width":9,"height":9,"length":9,"weight":9}]}';

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

    private function givenAppWithMockedApiResponse(): \Slim\App
    {
        $apiResponseFixture = file_get_contents(__DIR__ . '/../ApiResponseExamples/expected_api_response.json');
        $this->assertNotFalse($apiResponseFixture, 'Fixture tests/ApiResponseExamples/expected_api_response.json must be readable.');

        return $this->givenResponseFromApi($apiResponseFixture);
    }

    private function givenResponseFromApi(string $responseBody): \Slim\App
    {
        return $this->createApp([
            Client::class => static fn (): Client => new Client([
                'handler' => HandlerStack::create(new MockHandler([
                    new Response(200, ['Content-Type' => 'application/json'], $responseBody),
                ])),
            ]),
        ]);
    }

    private function whenPackEndpointIsCalledWithValidProducts(\Slim\App $app): ResponseInterface
    {
        return $this->whenPackEndpointIsCalledWithProducts($app, self::VALID_PRODUCTS);
    }

    private function whenPackEndpointIsCalledWithProducts(\Slim\App $app, string $products): ResponseInterface
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
}
