<?php

namespace App\Services;

use App\Entity\Packaging;
use App\Repository\PackagingRepository;
use App\Repository\PackerResponseCacheRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class PackingService
{
    public function __construct(
        private string $apiUrl,
        private string $apiKey,
        private string $username,
        private Client $client,
        private PackagingRepository $packagingRepository,
        private LoggerInterface $logger,
        private PackerResponseCacheRepository $packerResponseCacheRepository
    )
    {

    }

    public function getOptimalBox(array $products): void
    {
        $items = $this->canonicalizeItems($this->prepareProducts($products));
        $canonizedItemsString = json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $requestHash = hash('sha256', $canonizedItemsString);
        $cached = $this->packerResponseCacheRepository->findByRequestHash($requestHash);
        if ($cached !== null) {
            $body = json_decode($cached->getResponseBody(), true);
            var_dump($body);
            // use $body['response'] same as when you get it from the API
            // ...
            return;
        }
        try {
            $response = $this->client->post("$this->apiUrl/packer/pack", [
                'json' => [
                    'bins' => $this->getAvailablePackings(),
                    'items' => $items,
                    'username' => $this->username,
                    'api_key' => $this->apiKey,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $body = json_decode($response->getBody()->getContents(), true);

                $response = $body['response'];
                $binsPacked = $response['bins_packed'];
                $binData = $binsPacked[0]['bin_data'];
                var_dump($binData);
            } else {
                $this->logger->error('Third party api error', ['exception' => $response]);
            }

        } catch (GuzzleException $e) {
            $this->logger->error('Packing service error', ['exception' => $e]);
        }




//        var_dump($response->getStatusCode());
//        $response = json_decode($response->getBody()->getContents(), true);
//        var_dump($response);
//        $binsPacked = $response['response'];
//        $binsPacked = $response['response']['bins_packed'];

//        var_dump($binsPacked);
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
            'id' => 'Pack ' . $packaging->id,
            'h' => $packaging->height,
            'w' => $packaging->width,
            'd' => $packaging->length,
            'max_wg' => $packaging->maxWeight,
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

    private function canonicalizeItems(array $items): array
    {
        ksort($items, SORT_STRING);

        foreach ($items as &$value) {
            if (is_array($value)) {
                ksort($value, SORT_STRING);
            }
        }

        return $items;
    }
}
