<?php

namespace App\Services;

/**
 * Calculates the optimal (smallest) packaging locally by volume and weight,
 * without calling the third-party packer API.
 */
class LocalPackagingCalculator
{
    /**
     * Selects the smallest bin that can fit all items by total volume and weight.
     *
     * @param list<array{id: int|string, h: float, w: float, d: float, max_wg: float, type?: string}> $bins Available packagings (same format as PackingService::getAvailablePackings)
     * @param list<array{id: string, w: int|float, h: int|float, d: int|float, q: int, wg: int|float, vr?: int}> $items Items to pack (same format as prepared/canonicalized items)
     * @return array{id: int|string}|array{} Bin data with 'id' of the chosen packaging, or empty array if none fits
     */
    public function calculateOptimalBin(array $bins, array $items): array
    {
        $totalVolume = $this->totalItemsVolume($items);
        $totalWeight = $this->totalItemsWeight($items);

        if ($totalVolume <= 0.0 && $totalWeight <= 0.0) {
            return [];
        }

        $sortedBins = $this->sortBinsByVolumeAsc($bins);

        foreach ($sortedBins as $bin) {
            $binVolume = (float) $bin['w'] * (float) $bin['h'] * (float) $bin['d'];
            $maxWeight = (float) ($bin['max_wg'] ?? 0);

            if ($binVolume >= $totalVolume && $maxWeight >= $totalWeight) {
                return ['id' => $bin['id']];
            }
        }

        return [];
    }

    /**
     * @param list<array{w: int|float, h: int|float, d: int|float, q: int}> $items
     */
    private function totalItemsVolume(array $items): float
    {
        $volume = 0.0;
        foreach ($items as $item) {
            $q = (int) ($item['q'] ?? 1);
            $volume += (float) $item['w'] * (float) $item['h'] * (float) $item['d'] * $q;
        }
        return $volume;
    }

    /**
     * @param list<array{wg: int|float, q: int}> $items
     */
    private function totalItemsWeight(array $items): float
    {
        $weight = 0.0;
        foreach ($items as $item) {
            $q = (int) ($item['q'] ?? 1);
            $weight += (float) $item['wg'] * $q;
        }
        return $weight;
    }

    /**
     * @param list<array{id: int|string, h: float, w: float, d: float}> $bins
     * @return list<array{id: int|string, h: float, w: float, d: float, max_wg: float}>
     */
    private function sortBinsByVolumeAsc(array $bins): array
    {
        $withVolume = [];
        foreach ($bins as $bin) {
            $withVolume[] = [
                'volume' => (float) $bin['w'] * (float) $bin['h'] * (float) $bin['d'],
                'bin' => $bin,
            ];
        }
        usort($withVolume, static fn ($a, $b) => $a['volume'] <=> $b['volume']);
        return array_map(static fn ($entry) => $entry['bin'], $withVolume);
    }
}
