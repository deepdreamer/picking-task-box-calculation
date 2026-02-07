<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\Exception\NoAppropriatePackagingFoundException;
use App\Services\LocalPackagingCalculator;
use App\Services\Exception\NonPositiveItemVolumeException;
use App\Services\Exception\NonPositiveItemWeightException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\InvalidParameterException;

class LocalPackagingCalculatorTest extends TestCase
{
    private LocalPackagingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new LocalPackagingCalculator();
    }

    public function testCalculateOptimalBinReturnsSmallestFittingBin(): void
    {
        $bins = [
            ['id' => 'large', 'w' => 100.0, 'h' => 100.0, 'd' => 100.0, 'max_wg' => 1000.0],
            ['id' => 'small', 'w' => 10.0, 'h' => 10.0, 'd' => 10.0, 'max_wg' => 50.0],
            ['id' => 'medium', 'w' => 50.0, 'h' => 50.0, 'd' => 50.0, 'max_wg' => 500.0],
        ];
        $items = [
            ['id' => 'item1', 'w' => 2.0, 'h' => 2.0, 'd' => 2.0, 'q' => 1, 'wg' => 1.0],
        ];

        $result = $this->calculator->calculateOptimalBin($bins, $items);

        $this->assertSame(['id' => 'small'], $result);
    }

    public function testCalculateOptimalBinRespectsWeightLimit(): void
    {
        $bins = [
            ['id' => 'light-bin', 'w' => 20.0, 'h' => 20.0, 'd' => 20.0, 'max_wg' => 5.0],
            ['id' => 'heavy-bin', 'w' => 20.0, 'h' => 20.0, 'd' => 20.0, 'max_wg' => 100.0],
        ];
        $items = [
            ['id' => 'item1', 'w' => 5.0, 'h' => 5.0, 'd' => 5.0, 'q' => 1, 'wg' => 10.0],
        ];

        $result = $this->calculator->calculateOptimalBin($bins, $items);

        $this->assertSame(['id' => 'heavy-bin'], $result);
    }

    public function testCalculateOptimalBinThrowsWhenNoBinFits(): void
    {
        $bins = [
            ['id' => 'tiny', 'w' => 5.0, 'h' => 5.0, 'd' => 5.0, 'max_wg' => 10.0],
        ];
        $items = [
            ['id' => 'item1', 'w' => 10.0, 'h' => 10.0, 'd' => 10.0, 'q' => 1, 'wg' => 5.0],
        ];

        $this->expectException(NoAppropriatePackagingFoundException::class);

        $this->calculator->calculateOptimalBin($bins, $items);
    }

    public function testCalculateOptimalBinThrowsWhenNoBinFitsByWeight(): void
    {
        $bins = [
            ['id' => 'bin', 'w' => 100.0, 'h' => 100.0, 'd' => 100.0, 'max_wg' => 5.0],
        ];
        $items = [
            ['id' => 'item1', 'w' => 1.0, 'h' => 1.0, 'd' => 1.0, 'q' => 1, 'wg' => 10.0],
        ];

        $this->expectException(NoAppropriatePackagingFoundException::class);

        $this->calculator->calculateOptimalBin($bins, $items);
    }

    public function testCalculateOptimalBinThrowsOnZeroOrNegativeVolume(): void
    {
        $bins = [
            ['id' => 'bin', 'w' => 100.0, 'h' => 100.0, 'd' => 100.0, 'max_wg' => 1000.0],
        ];
        $items = [
            ['id' => 'item1', 'w' => 0.0, 'h' => 10.0, 'd' => 10.0, 'q' => 1, 'wg' => 1.0],
        ];

        $this->expectException(NonPositiveItemVolumeException::class);

        $this->calculator->calculateOptimalBin($bins, $items);
    }

    public function testCalculateOptimalBinThrowsOnZeroOrNegativeWeight(): void
    {
        $bins = [
            ['id' => 'bin', 'w' => 100.0, 'h' => 100.0, 'd' => 100.0, 'max_wg' => 1000.0],
        ];
        $items = [
            ['id' => 'item1', 'w' => 10.0, 'h' => 10.0, 'd' => 10.0, 'q' => 1, 'wg' => 0.0],
        ];

        $this->expectException(NonPositiveItemWeightException::class);

        $this->calculator->calculateOptimalBin($bins, $items);
    }

    public function testCalculateOptimalBinThrowsOnEmptyItems(): void
    {
        $bins = [
            ['id' => 'bin', 'w' => 10.0, 'h' => 10.0, 'd' => 10.0, 'max_wg' => 100.0],
        ];
        $items = [];

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('bins and items cannot be empty');

        $this->calculator->calculateOptimalBin($bins, $items);
    }

    public function testCalculateOptimalBinHandlesMultipleItemsWithQuantity(): void
    {
        $bins = [
            ['id' => 'bin', 'w' => 20.0, 'h' => 20.0, 'd' => 20.0, 'max_wg' => 100.0],
        ];
        $items = [
            ['id' => 'item1', 'w' => 2.0, 'h' => 2.0, 'd' => 2.0, 'q' => 3, 'wg' => 5.0],
            ['id' => 'item2', 'w' => 1.0, 'h' => 1.0, 'd' => 1.0, 'q' => 2, 'wg' => 2.0],
        ];
        // total volume: 2*2*2*3 + 1*1*1*2 = 24 + 2 = 26
        // total weight: 5*3 + 2*2 = 15 + 4 = 19
        // bin volume: 20*20*20 = 8000, max_wg: 100 -> fits

        $result = $this->calculator->calculateOptimalBin($bins, $items);

        $this->assertSame(['id' => 'bin'], $result);
    }
}
