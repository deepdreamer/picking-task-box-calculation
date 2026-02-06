<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\LocalPackagingCalculator;
use App\Services\Exception\CannotFitInOneBinException;
use App\Services\Exception\NonPositiveItemVolumeException;
use App\Services\Exception\NonPositiveItemWeightException;
use App\Services\Exception\TotalItemsDimensionsException;
use PHPUnit\Framework\TestCase;

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
        // @todo Given bins sorted by volume, returns bin with smallest volume that fits items
        $this->markTestIncomplete('Not implemented');
    }

    public function testCalculateOptimalBinRespectsWeightLimit(): void
    {
        // @todo Bin with enough volume but insufficient max_weight excluded
        $this->markTestIncomplete('Not implemented');
    }

    public function testCalculateOptimalBinThrowsWhenNoBinFits(): void
    {
        // @todo Items exceed all bins volume/weight -> CannotFitInOneBinException
        $this->markTestIncomplete('Not implemented');
    }

    public function testCalculateOptimalBinThrowsOnZeroOrNegativeVolume(): void
    {
        // @todo Item with zero/negative dimension -> TotalItemsDimensionsException or NonPositiveItemVolumeException
        $this->markTestIncomplete('Not implemented');
    }

    public function testCalculateOptimalBinThrowsOnZeroOrNegativeWeight(): void
    {
        // @todo Item with zero/negative weight -> NonPositiveItemWeightException
        $this->markTestIncomplete('Not implemented');
    }

    public function testCalculateOptimalBinHandlesMultipleItemsWithQuantity(): void
    {
        // @todo Items with q > 1 - total volume/weight calculated correctly
        $this->markTestIncomplete('Not implemented');
    }
}
