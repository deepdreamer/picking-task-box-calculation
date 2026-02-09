<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\Exception\InputValidationException;
use App\Services\InputValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class InputValidatorTest extends TestCase
{
    private InputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new InputValidator();
    }

    #[DataProvider('validProductsProvider')]
    public function testGetProductsReturnsValidProductsArray(array $payload, array $expected): void
    {
        $request = $this->givenRequestWithBody(self::toJson($payload));

        $result = $this->validator->getProducts($request);

        $this->assertSame($expected, $result);
    }

    public static function validProductsProvider(): array
    {
        return [
            'single product' => [
                [
                    'products' => [
                        ['id' => 1, 'width' => 5, 'height' => 10, 'length' => 15, 'weight' => 20],
                    ],
                ],
                [['width' => 5.0, 'height' => 10.0, 'length' => 15.0, 'weight' => 20.0]],
            ],
            'decimal values' => [
                [
                    'products' => [
                        ['id' => 1, 'width' => 2.5, 'height' => 3.7, 'length' => 4.2, 'weight' => 1.8],
                    ],
                ],
                [['width' => 2.5, 'height' => 3.7, 'length' => 4.2, 'weight' => 1.8]],
            ],
            'multiple products' => [
                [
                    'products' => [
                        ['id' => 1, 'width' => 1, 'height' => 2, 'length' => 3.5, 'weight' => 1],
                        ['id' => 2, 'width' => 10, 'height' => 20, 'length' => 30, 'weight' => 5],
                    ],
                ],
                [
                    ['width' => 1.0, 'height' => 2.0, 'length' => 3.5, 'weight' => 1.0],
                    ['width' => 10.0, 'height' => 20.0, 'length' => 30.0, 'weight' => 5.0],
                ],
            ],
            'mixed scale values' => [
                [
                    'products' => [
                        ['id' => 1, 'width' => 0.5, 'height' => 0.5, 'length' => 0.5, 'weight' => 0.1],
                        ['id' => 2, 'width' => 100, 'height' => 200, 'length' => 300, 'weight' => 500],
                        ['id' => 3, 'width' => 2.5, 'height' => 3.7, 'length' => 4.2, 'weight' => 1.8],
                    ],
                ],
                [
                    ['width' => 0.5, 'height' => 0.5, 'length' => 0.5, 'weight' => 0.1],
                    ['width' => 100.0, 'height' => 200.0, 'length' => 300.0, 'weight' => 500.0],
                    ['width' => 2.5, 'height' => 3.7, 'length' => 4.2, 'weight' => 1.8],
                ],
            ],
        ];
    }

    #[DataProvider('notJsonArrayProvider')]
    public function testGetProductsThrowsWhenInputIsNotJsonArray(string $body): void
    {
        $request = $this->givenRequestWithBody($body);

        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('Products must be a JSON array.');

        $this->validator->getProducts($request);
    }

    public static function notJsonArrayProvider(): array
    {
        return [
            'invalid JSON' => ['invalid JSON'],
            'empty string' => [''],
            'products key missing' => [self::toJson(['foo' => []])],
            'products not an array' => [self::toJson(['products' => 'invalid'])],
        ];
    }

    #[DataProvider('productMissingRequiredKeyProvider')]
    public function testGetProductsThrowsWhenProductIsMissingRequiredKey(array $payload, string $expectedMessage): void
    {
        $request = $this->givenRequestWithBody(self::toJson($payload));

        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->getProducts($request);
    }

    public static function productMissingRequiredKeyProvider(): array
    {
        return [
            'missing id' => [
                ['products' => [['width' => 5, 'height' => 10, 'length' => 15, 'weight' => 20]]],
                "Product at index 0 is missing key 'id'.",
            ],
            'missing width' => [
                ['products' => [['id' => 1, 'height' => 10, 'length' => 15, 'weight' => 20]]],
                "Product at index 0 is missing key 'width'.",
            ],
            'missing height' => [
                ['products' => [['id' => 1, 'width' => 5, 'length' => 15, 'weight' => 20]]],
                "Product at index 0 is missing key 'height'.",
            ],
            'missing length' => [
                ['products' => [['id' => 1, 'width' => 5, 'height' => 10, 'weight' => 20]]],
                "Product at index 0 is missing key 'length'.",
            ],
            'missing weight' => [
                ['products' => [['id' => 1, 'width' => 5, 'height' => 10, 'length' => 15]]],
                "Product at index 0 is missing key 'weight'.",
            ],
        ];
    }

    #[DataProvider('valueNotNumericProvider')]
    public function testGetProductsThrowsWhenValueIsNotNumeric(array $payload, string $expectedMessage): void
    {
        $request = $this->givenRequestWithBody(self::toJson($payload));

        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->getProducts($request);
    }

    public static function valueNotNumericProvider(): array
    {
        return [
            'id is string' => [
                ['products' => [['id' => 'abc', 'width' => 5, 'height' => 10, 'length' => 15, 'weight' => 20]]],
                "Product at index 0, key 'id' must be an number.",
            ],
            'width is string' => [
                ['products' => [['id' => 1, 'width' => 'abc', 'height' => 10, 'length' => 15, 'weight' => 20]]],
                "Product at index 0, key 'width' must be an number.",
            ],
            'height is boolean' => [
                ['products' => [['id' => 1, 'width' => 5, 'height' => true, 'length' => 15, 'weight' => 20]]],
                "Product at index 0, key 'height' must be an number.",
            ],
            'length is string' => [
                ['products' => [['id' => 1, 'width' => 5, 'height' => 10, 'length' => 'x', 'weight' => 20]]],
                "Product at index 0, key 'length' must be an number.",
            ],
            'weight is boolean' => [
                ['products' => [['id' => 1, 'width' => 5, 'height' => 10, 'length' => 15, 'weight' => false]]],
                "Product at index 0, key 'weight' must be an number.",
            ],
        ];
    }

    #[DataProvider('productHasUnexpectedKeysProvider')]
    public function testGetProductsThrowsWhenProductHasUnexpectedKeys(array $payload, string $expectedMessage): void
    {
        $request = $this->givenRequestWithBody(self::toJson($payload));

        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->getProducts($request);
    }

    public static function productHasUnexpectedKeysProvider(): array
    {
        return [
            'extra key name' => [
                ['products' => [['id' => 1, 'width' => 1, 'height' => 2, 'length' => 3, 'weight' => 4, 'name' => 'foo']]],
                "Product at index 0 has unexpected keys: name",
            ],
            'extra keys sku and name' => [
                ['products' => [['id' => 1, 'width' => 1, 'height' => 2, 'length' => 3, 'weight' => 4, 'sku' => 123, 'name' => 'foo']]],
                "Product at index 0 has unexpected keys: sku, name",
            ],
        ];
    }

    #[DataProvider('emptyPayloadProvider')]
    public function testGetProductsThrowsWhenPayloadIsEmpty(array $payload, string $expectedMessage): void
    {
        $request = $this->givenRequestWithBody(self::toJson($payload));

        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->getProducts($request);
    }

    public static function emptyPayloadProvider(): array
    {
        return [
            'empty root array' => [
                [],
                'Product list must not be empty.',
            ],
            'empty products array' => [
                ['products' => []],
                'Product list must not be empty.',
            ],
        ];
    }

    private function givenRequestWithBody(string $body): ServerRequestInterface
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn($body);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getBody')->willReturn($stream);

        return $request;
    }

    private static function toJson(array $payload): string
    {
        return (string) json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
