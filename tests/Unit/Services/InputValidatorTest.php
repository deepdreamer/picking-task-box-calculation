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
    public function testGetProductsReturnsValidProductsArray(string $json, array $expected): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn($json);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getBody')->willReturn($stream);

        $validator = new InputValidator();

        $result = $validator->getProducts($request);

        $this->assertSame($expected, $result);
    }

    public static function validProductsProvider(): array
    {
        return [
            [
                '[{"width":5,"height":10,"length":15,"weight":20}]',
                [['width' => 5.0, 'height' => 10.0, 'length' => 15.0, 'weight' => 20.0]],
            ],
            [
                '[{"width":2.5,"height":3.7,"length":4.2,"weight":1.8}]',
                [['width' => 2.5, 'height' => 3.7, 'length' => 4.2, 'weight' => 1.8]],
            ],
            [
                '[{"width":1,"height":2,"length":3.5,"weight":1},{"width":10,"height":20,"length":30,"weight":5}]',
                [['width' => 1.0, 'height' => 2.0, 'length' => 3.5, 'weight' => 1.0], ['width' => 10.0, 'height' => 20.0, 'length' => 30.0, 'weight' => 5.0]],
            ],
            [
                '[{"width":0.5,"height":0.5,"length":0.5,"weight":0.1},{"width":100,"height":200,"length":300,"weight":500},{"width":2.5,"height":3.7,"length":4.2,"weight":1.8}]',
                [['width' => 0.5, 'height' => 0.5, 'length' => 0.5, 'weight' => 0.1], ['width' => 100.0, 'height' => 200.0, 'length' => 300.0, 'weight' => 500.0], ['width' => 2.5, 'height' => 3.7, 'length' => 4.2, 'weight' => 1.8]],
            ],
            [
                '[{"width":999.99,"height":888.88,"length":777.77,"weight":666.66}]',
                [['width' => 999.99, 'height' => 888.88, 'length' => 777.77, 'weight' => 666.66]],
            ],
        ];
    }

    #[DataProvider('notJsonArrayProvider')]
    public function testGetProductsThrowsWhenInputIsNotJsonArray(string $body): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn($body);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getBody')->willReturn($stream);

        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('Products must be a JSON array.');

        $this->validator->getProducts($request);
    }

    public static function notJsonArrayProvider(): array
    {
        return [
            'invalid JSON' => ['invalid JSON'],
            'empty string' => [''],
        ];
    }

    #[DataProvider('productMissingRequiredKeyProvider')]
    public function testGetProductsThrowsWhenProductIsMissingRequiredKey(string $json, string $expectedMessage): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn($json);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getBody')->willReturn($stream);

        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->getProducts($request);
    }

    public static function productMissingRequiredKeyProvider(): array
    {
        return [
            'missing width' => [
                '[{"height":10,"length":15,"weight":20}]',
                "Product at index 0 is missing key 'width'.",
            ],
            'missing height' => [
                '[{"width":5,"length":15,"weight":20}]',
                "Product at index 0 is missing key 'height'.",
            ],
            'missing length' => [
                '[{"width":5,"height":10,"weight":20}]',
                "Product at index 0 is missing key 'length'.",
            ],
            'missing weight' => [
                '[{"width":5,"height":10,"length":15}]',
                "Product at index 0 is missing key 'weight'.",
            ],
            'second product missing width' => [
                '[{"width":1,"height":2,"length":3,"weight":4},{"height":10,"length":15,"weight":20}]',
                "Product at index 1 is missing key 'width'.",
            ],
        ];
    }

    #[DataProvider('valueNotNumericProvider')]
    public function testGetProductsThrowsWhenValueIsNotNumeric(string $json, string $expectedMessage): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn($json);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getBody')->willReturn($stream);

        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->getProducts($request);
    }

    public static function valueNotNumericProvider(): array
    {
        return [
            'width is string' => [
                '[{"width":"abc","height":10,"length":15,"weight":20}]',
                "Product at index 0, key 'width' must be an number.",
            ],
            'height is boolean' => [
                '[{"width":5,"height":true,"length":15,"weight":20}]',
                "Product at index 0, key 'height' must be an number.",
            ],
            'length is string' => [
                '[{"width":5,"height":10,"length":"x","weight":20}]',
                "Product at index 0, key 'length' must be an number.",
            ],
            'weight is boolean' => [
                '[{"width":5,"height":10,"length":15,"weight":false}]',
                "Product at index 0, key 'weight' must be an number.",
            ],
        ];
    }

    #[DataProvider('productHasUnexpectedKeysProvider')]
    public function testGetProductsThrowsWhenProductHasUnexpectedKeys(string $json, string $expectedMessage): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn($json);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getBody')->willReturn($stream);

        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->getProducts($request);
    }

    public static function productHasUnexpectedKeysProvider(): array
    {
        return [
            'extra key id' => [
                '[{"width":1,"height":2,"length":3,"weight":4,"id":123}]',
                "Product at index 0 has unexpected keys: id",
            ],
            'extra keys id and name' => [
                '[{"width":1,"height":2,"length":3,"weight":4,"id":123,"name":"foo"}]',
                "Product at index 0 has unexpected keys: id, name",
            ],
        ];
    }

    public function testGetProductsThrowsWhenEmptyArray(): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn('[]');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getBody')->willReturn($stream);

        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('Product list must not be empty.');

        $this->validator->getProducts($request);

        $stream->method('getContents')->willReturn('[{}]');

        $this->validator->getProducts($request);
    }
}
