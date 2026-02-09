<?php

declare(strict_types=1);

namespace App\Services;

class ProductNormalizer
{
    /**
     * @param list<array{width: int|float, height: int|float, length: int|float, weight: int|float}> $products
     * @return list<array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}>
     */
    public function normalizeProducts(array $products): array
    {
        return array_values($this->canonicalizeItems($this->prepareProducts($products)));
    }

    /**
     * @param list<array{width: int|float, height: int|float, length: int|float, weight: int|float}> $products
     */
    public function buildRequestHash(array $products): string
    {
        $items = $this->normalizeProducts($products);
        $canonizedItemsString = json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $canonizedItemsString !== false ? $canonizedItemsString : '');
    }

    /**
     * @param list<array{width: int|float, height: int|float, length: int|float, weight: int|float}> $products
     * @return list<array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}>
     */
    private function prepareProducts(array $products): array
    {
        return array_map(
            fn (array $product, int $index) => [
                'id' => 'Item' . ($index + 1),
                'w' => (int) ceil((float) $product['width']),
                'h' => (int) ceil((float) $product['height']),
                'd' => (int) ceil((float) $product['length']),
                'q' => 1,
                'wg' => (int) ceil((float) $product['weight']),
                'vr' => 1,
            ],
            $products,
            array_keys($products)
        );
    }

    /**
     * @param list<array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}> $items
     * @return array<int, array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}>
     */
    private function canonicalizeItems(array $items): array
    {
        usort(
            $items,
            static fn (array $a, array $b): int => [
                $a['w'],
                $a['h'],
                $a['d'],
                $a['wg'],
                $a['q'],
                $a['vr'],
            ] <=> [
                $b['w'],
                $b['h'],
                $b['d'],
                $b['wg'],
                $b['q'],
                $b['vr'],
            ]
        );

        foreach ($items as $index => &$item) {
            $item['id'] = 'Item' . ($index + 1);
        }
        unset($item);

        return $items;
    }
}
