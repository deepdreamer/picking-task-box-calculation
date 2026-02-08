<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\Exception\InvalidParameterException;
use App\Services\Exception\NoAppropriatePackagingFoundException;
use App\Services\LocalPackagingCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocalPackagingCalculatorTest extends TestCase
{
    private LocalPackagingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new LocalPackagingCalculator();
    }

    #[DataProvider('successfulSelectionProvider')]
    public function testCalculateOptimalBinSelectsExpectedBin(array $bins, array $items, array $expected): void
    {
        $result = $this->calculator->calculateOptimalBin($bins, $items);

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{0: list<array{id: string, w: float, h: float, d: float, max_wg: float}>, 1: array<int, array{id: string, w: float, h: float, d: float, wg: float}>, 2: array{id: string}}>
     */
    public static function successfulSelectionProvider(): array
    {
        return [
            'selects smallest fitting bin by volume' => [
                [
                    ['id' => 'large', 'w' => 100.0, 'h' => 100.0, 'd' => 100.0, 'max_wg' => 1000.0],
                    ['id' => 'small', 'w' => 10.0, 'h' => 10.0, 'd' => 10.0, 'max_wg' => 50.0],
                    ['id' => 'medium', 'w' => 50.0, 'h' => 50.0, 'd' => 50.0, 'max_wg' => 500.0],
                ],
                [
                    ['id' => 'item1', 'w' => 2.0, 'h' => 2.0, 'd' => 2.0, 'wg' => 1.0],
                ],
                ['id' => 'small'],
            ],
            'respects weight limit when dimensions equal' => [
                [
                    ['id' => 'light-bin', 'w' => 20.0, 'h' => 20.0, 'd' => 20.0, 'max_wg' => 5.0],
                    ['id' => 'heavy-bin', 'w' => 20.0, 'h' => 20.0, 'd' => 20.0, 'max_wg' => 100.0],
                ],
                [
                    ['id' => 'item1', 'w' => 5.0, 'h' => 5.0, 'd' => 5.0, 'wg' => 10.0],
                ],
                ['id' => 'heavy-bin'],
            ],
            'handles quantities in totals' => [
                [
                    ['id' => 'bin', 'w' => 20.0, 'h' => 20.0, 'd' => 20.0, 'max_wg' => 100.0],
                ],
                [
                    ['id' => 'item1', 'w' => 2.0, 'h' => 2.0, 'd' => 2.0, 'wg' => 5.0],
                    ['id' => 'item2', 'w' => 2.0, 'h' => 2.0, 'd' => 2.0, 'wg' => 5.0],
                    ['id' => 'item3', 'w' => 2.0, 'h' => 2.0, 'd' => 2.0, 'wg' => 5.0],
                    ['id' => 'item4', 'w' => 1.0, 'h' => 1.0, 'd' => 1.0, 'wg' => 2.0],
                    ['id' => 'item5', 'w' => 1.0, 'h' => 1.0, 'd' => 1.0, 'wg' => 2.0],
                ],
                ['id' => 'bin'],
            ],
            'allows rotation' => [
                [
                    ['id' => 'narrow', 'w' => 5.0, 'h' => 4.0, 'd' => 3.0, 'max_wg' => 20.0],
                ],
                [
                    ['id' => 'item1', 'w' => 3.0, 'h' => 5.0, 'd' => 4.0, 'wg' => 2.0],
                ],
                ['id' => 'narrow'],
            ],
        ];
    }

    #[DataProvider('noAppropriatePackagingFoundProvider')]
    public function testCalculateOptimalBinThrowsNoAppropriatePackagingFound(array $bins, array $items): void
    {
        $this->expectException(NoAppropriatePackagingFoundException::class);

        $this->calculator->calculateOptimalBin($bins, $items);
    }

    /**
     * @return array<string, array{0: list<array{id: string, w: float, h: float, d: float, max_wg: float}>, 1: array<int, array{id: string, w: float, h: float, d: float, wg: float}>}>
     */
    public static function noAppropriatePackagingFoundProvider(): array
    {
        return [
            'no bin fits by dimensions (item larger than bin)' => [
                [
                    ['id' => 'tiny', 'w' => 5.0, 'h' => 5.0, 'd' => 5.0, 'max_wg' => 10.0],
                ],
                [
                    ['id' => 'item1', 'w' => 10.0, 'h' => 10.0, 'd' => 10.0, 'wg' => 5.0],
                ],
            ],
            'no bin fits by weight (item heavier than bin max_wg)' => [
                [
                    ['id' => 'bin', 'w' => 100.0, 'h' => 100.0, 'd' => 100.0, 'max_wg' => 5.0],
                ],
                [
                    ['id' => 'item1', 'w' => 1.0, 'h' => 1.0, 'd' => 1.0, 'wg' => 10.0],
                ],
            ],
            'multiple items exceed single-bin max weight' => [
                [
                    ['id' => 'small', 'w' => 10.0, 'h' => 10.0, 'd' => 10.0, 'max_wg' => 50.0],
                    ['id' => 'large', 'w' => 100.0, 'h' => 100.0, 'd' => 100.0, 'max_wg' => 1000.0],
                ],
                [
                    ['id' => 'item1', 'w' => 10.0, 'h' => 10.0, 'd' => 10.0, 'wg' => 600.0],
                    ['id' => 'item2', 'w' => 10.0, 'h' => 10.0, 'd' => 10.0, 'wg' => 600.0],
                ],
            ],
            'edge length prevents fit even when total volume and weight fit' => [
                [
                    ['id' => 'bin', 'w' => 10.0, 'h' => 10.0, 'd' => 10.0, 'max_wg' => 100.0],
                ],
                [
                    ['id' => 'item1', 'w' => 12.0, 'h' => 8.0, 'd' => 8.0, 'wg' => 5.0],
                ],
            ],
            'no orientation can fit item into narrow bin' => [
                [
                    ['id' => 'narrow', 'w' => 5.0, 'h' => 4.0, 'd' => 3.0, 'max_wg' => 20.0],
                ],
                [
                    ['id' => 'item1', 'w' => 6.0, 'h' => 3.0, 'd' => 2.0, 'wg' => 2.0],
                ],
            ],
        ];
    }

    #[DataProvider('invalidInputProvider')]
    public function testCalculateOptimalBinThrowsOnInvalidInput(array $bins, array $items): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('bins and items cannot be empty');

        $this->calculator->calculateOptimalBin($bins, $items);
    }

    /**
     * @return array<string, array{0: list<array{id: string, w: float, h: float, d: float, max_wg: float}>, 1: array<int, array{id: string, w: float, h: float, d: float, wg: float}>}>
     */
    public static function invalidInputProvider(): array
    {
        return [
            'empty items' => [
                [
                    ['id' => 'bin', 'w' => 10.0, 'h' => 10.0, 'd' => 10.0, 'max_wg' => 100.0],
                ],
                [],
            ],
            'empty bins' => [
                [],
                [
                    ['id' => 'item1', 'w' => 1.0, 'h' => 1.0, 'd' => 1.0, 'wg' => 1.0],
                ],
            ],
        ];
    }
}
