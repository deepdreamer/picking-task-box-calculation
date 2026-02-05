<?php

namespace App\Services;

use App\Entity\Packaging;
use App\Repository\PackagingRepository;
use GuzzleHttp\Client;

class PackingService
{
    public function __construct(
        private string $apiUrl,
        private string $apiKey,
        private string $username,
        private Client $client,
        private PackagingRepository $packagingRepository,
    )
    {

    }

    public function getOptimalBox(array $products): void
    {
        $response = $this->client->post("$this->apiUrl/packer/findBinSize", [
            'json' => [
                'bins' => $this->getAvailablePackings(),
                'items' => $this->prepareProducts($products),
                'username' => $this->username,
                'api_key' => $this->apiKey,
            ],
        ]);

        var_dump($response->getBody()->getContents());
    }

    /**
     * @return array<int, array{id: string, h: float, w: float, d: float, wg: string, max_wg: float, q: null, cost: int, type: string}>
     */
    private function getAvailablePackings(): array
    {
        $packings = $this->packagingRepository->findAll();

        return array_map(
            fn(Packaging $packaging) => $this->packagingToBin($packaging),
            $packings
        );
    }

    /**
     * @return array{id: string, h: float, w: float, d: float, wg: string, max_wg: float, q: null, cost: int, type: string}
     */
    private function packagingToBin(Packaging $packaging): array
    {
        return [
            'id' => 'Pack ' . $packaging->getId(),
            'h' => $packaging->getHeight(),
            'w' => $packaging->getWidth(),
            'd' => $packaging->getLength(),
            'max_wg' => $packaging->getMaxWeight(),
            'type' => 'box',
        ];
    }

    /**
     * @param list<array{width: int, height: int, length: int, weight: int}> $products
     * @return list<array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}>
     */
    private function prepareProducts(array $products): array
    {
        return array_map(
            fn(array $product, int $index) => [
                'id' => 'Item' . ($index + 1),
                'w' => $product['width'],
                'h' => $product['height'],
                'd' => $product['length'],
                'q' => 1,
                'wg' => $product['weight'],
                'vr' => 1,
            ],
            $products,
            array_keys($products)
        );
    }
}
