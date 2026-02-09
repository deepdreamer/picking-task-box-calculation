<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Exception\InvalidParameterException;
use App\Services\Exception\NoAppropriatePackagingFoundException;

/**
 * Calculates the optimal (smallest) packaging locally by volume and weight,
 * without calling the third-party packer API.
 */
class LocalPackagingCalculator
{
    /**
     * Selects the smallest bin that can fit all items by total volume and weight.
     *
     * @param list<array{id: int|string, h: float, w: float, d: float, max_wg: float, type?: string}> $bins Available packagings
     * @param array<int, array{id: string, w: int|float, h: int|float, d: int|float, q?: int, wg: int|float, vr?: int}> $items Items to pack
     * @return array{id: int|string}|array{} Bin data with 'id' of the chosen packaging, or empty if none fits
     * @throws NoAppropriatePackagingFoundException|InvalidParameterException
     */
    public function calculateOptimalBin(array $bins, array $items): array
    {
        if ($bins === [] || $items === []) {
            throw new InvalidParameterException('bins and items cannot be empty');
        }

        $totalVolume = $this->totalItemsVolume($items);
        $totalWeight = $this->totalItemsWeight($items);

        $sortedBinsWithVolume = $this->sortBinsByVolumeAsc($bins);

        foreach ($sortedBinsWithVolume as $binWithVolume) {
            $binVolume = $binWithVolume['volume'];
            $bin = $binWithVolume['bin'];
            $maxWeight = $bin['max_wg'];

            if (
                $binVolume >= $totalVolume
                && $maxWeight >= $totalWeight
                && $this->allItemsCanFitIndividually($bin, $items)
            ) {
                return ['id' => $bin['id']];
            }
        }

        throw new NoAppropriatePackagingFoundException();
    }

    /**
     * @param array<int, array{w: int|float, h: int|float, d: int|float, q?: int}> $items
     */
    private function totalItemsVolume(array $items): float
    {
        $volume = 0.0;
        foreach ($items as $item) {
            $quantity = $item['q'] ?? 1;
            $volume += (float) $item['w'] * (float) $item['h'] * (float) $item['d'] * $quantity;
        }
        return $volume;
    }

    /**
     * @param array<int, array{wg: int|float, q?: int}> $items
     */
    private function totalItemsWeight(array $items): float
    {
        $weight = 0.0;
        foreach ($items as $item) {
            $quantity = $item['q'] ?? 1;
            $weight += (float) $item['wg'] * $quantity;
        }
        return $weight;
    }

    /**
     * @param list<array{id: int|string, h: float, w: float, d: float, max_wg: float}> $bins
     * @return list<array{volume: float, bin: array{id: int|string, h: float, w: float, d: float, max_wg: float}}>
     */
    private function sortBinsByVolumeAsc(array $bins): array
    {
        $withVolume = [];
        foreach ($bins as $bin) {
            $withVolume[] = [
                'volume' => $bin['w'] * $bin['h'] * $bin['d'],
                'bin' => $bin,
            ];
        }
        usort($withVolume, static fn (array $a, array $b): int => $a['volume'] <=> $b['volume']);

        return $withVolume;
    }

    /**
     * @param array{id: int|string, h: float, w: float, d: float, max_wg: float} $bin
     * @param array<int, array{id: string, w: int|float, h: int|float, d: int|float, q?: int, wg: int|float, vr?: int}> $items
     */
    private function allItemsCanFitIndividually(array $bin, array $items): bool
    {
        foreach ($items as $item) {
            if (!$this->canSingleItemFitBin($item, $bin)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a single item can fit into a bin. Imagine a bin with dimensions 10x10x10 (H x W x D) and an item with dimensions 5x20x10.
     * The volume of the item is 1000, which is less or equal to the volume of the bin (1000). However it cannot fit because it is too wide.
     * @param array{id: string, w: int|float, h: int|float, d: int|float, q?: int, wg: int|float, vr?: int} $item
     * @param array{id: int|string, h: float, w: float, d: float, max_wg: float} $bin
     */
    private function canSingleItemFitBin(array $item, array $bin): bool
    {
        $itemW = (int) ceil($item['w']);
        $itemH = (int) ceil($item['h']);
        $itemD = (int) ceil($item['d']);
        $binW = (int) ceil($bin['w']);
        $binH = (int) ceil($bin['h']);
        $binD = (int) ceil($bin['d']);

        $permutations = [
            [$itemW, $itemH, $itemD],
            [$itemW, $itemD, $itemH],
            [$itemH, $itemW, $itemD],
            [$itemH, $itemD, $itemW],
            [$itemD, $itemW, $itemH],
            [$itemD, $itemH, $itemW],
        ];

        foreach ($permutations as [$w, $h, $d]) {
            if ($w <= $binW && $h <= $binH && $d <= $binD) {
                return true;
            }
        }

        return false;
    }
}
