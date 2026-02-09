<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\Packaging;
use App\Services\OutputFormatter;
use PHPUnit\Framework\TestCase;

class OutputFormatterTest extends TestCase
{
    private OutputFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new OutputFormatter();
    }

    public function testFormatReturnsExpectedStructure(): void
    {
        $givenPackaging = $this->givenPackaging(10.0, 20.0, 30.0, 40.0, 101);

        $expectedResult = $this->whenFormatting($givenPackaging);

        $this->thenFormattedOutputEquals($expectedResult, [
            'success' => true,
            'box' => [
                'id' => 101,
                'width' => 10.0,
                'height' => 20.0,
                'length' => 30.0,
                'max_weight' => 40.0,
                'dimensions' => '10.00 × 20.00 × 30.00 cm',
            ],
        ]);
    }

    public function testFormatIncludesCorrectDimensionsString(): void
    {
        $givenPackaging = $this->givenPackaging(10.0, 20.0, 30.0, 40.0, 101);

        $expectedResult = $this->whenFormatting($givenPackaging);

        $this->thenDimensionsStringIs($expectedResult, '10.00 × 20.00 × 30.00 cm');
    }

    public function testToJsonReturnsValidJson(): void
    {
        $givenPackaging = $this->givenPackaging(10.0, 20.0, 30.0, 40.0, 101);

        $whenJson = $this->whenConvertingToJson($givenPackaging);

        $this->thenJsonOutputIsValidAndEqualsFormatted($whenJson, $this->whenFormatting($givenPackaging));
    }

    public function testFormatHandlesNullPackagingId(): void
    {
        $givenPackaging = $this->givenPackaging(10.0, 20.0, 30.0, 40.0, null);

        $expectedResult = $this->whenFormatting($givenPackaging);

        $this->thenFormattedOutputEquals($expectedResult, [
            'success' => true,
            'box' => [
                'id' => null,
                'width' => 10.0,
                'height' => 20.0,
                'length' => 30.0,
                'max_weight' => 40.0,
                'dimensions' => '10.00 × 20.00 × 30.00 cm',
            ],
        ]);
    }

    private function givenPackaging(
        float $width,
        float $height,
        float $length,
        float $maxWeight,
        ?int $id
    ): Packaging {
        $packaging = new Packaging($width, $height, $length, $maxWeight);
        $packaging->id = $id;

        return $packaging;
    }

    /**
     * @return array{
     *     success: bool,
     *     box: array{
     *         id: int|null,
     *         width: float,
     *         height: float,
     *         length: float,
     *         max_weight: float,
     *         dimensions: string
     *     }
     * }
     */
    private function whenFormatting(Packaging $packaging): array
    {
        return $this->formatter->format($packaging);
    }

    private function whenConvertingToJson(Packaging $packaging): string
    {
        return $this->formatter->toJson($packaging);
    }

    /**
     * @param array{
     *     success: bool,
     *     box: array{
     *         id: int|null,
     *         width: float,
     *         height: float,
     *         length: float,
     *         max_weight: float,
     *         dimensions: string
     *     }
     * } $actual
     * @param array{
     *     success: bool,
     *     box: array{
     *         id: int|null,
     *         width: float,
     *         height: float,
     *         length: float,
     *         max_weight: float,
     *         dimensions: string
     *     }
     * } $expected
     */
    private function thenFormattedOutputEquals(array $actual, array $expected): void
    {
        $this->assertSame($expected, $actual);
    }

    /**
     * @param array{
     *     success: bool,
     *     box: array{
     *         id: int|null,
     *         width: float,
     *         height: float,
     *         length: float,
     *         max_weight: float,
     *         dimensions: string
     *     }
     * } $formattedResult
     */
    private function thenDimensionsStringIs(array $formattedResult, string $expectedDimensions): void
    {
        $this->assertSame($expectedDimensions, $formattedResult['box']['dimensions']);
    }

    /**
     * @param array{
     *     success: bool,
     *     box: array{
     *         id: int|null,
     *         width: float,
     *         height: float,
     *         length: float,
     *         max_weight: float,
     *         dimensions: string
     *     }
     * } $expectedFormatted
     */
    private function thenJsonOutputIsValidAndEqualsFormatted(string $json, array $expectedFormatted): void
    {
        $this->assertJson($json);
        $expectedJson = json_encode($expectedFormatted, JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJson, $json);
    }
}
