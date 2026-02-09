<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Exception\ApiErrorException;
use App\Services\Exception\NoAppropriatePackagingFoundException;
use App\Services\Exception\UnexpectedApiResponseFormatException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class PackingApiClient
{
    public function __construct(
        private string $apiUrl,
        private string $apiKey,
        private string $username,
        private string $appEnv,
        private Client $client,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<array{id: string, h: float, w: float, d: float, max_wg: float, q: int, type: string}> $bins
     * @param array<int, array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}> $items
     * @return array<string, mixed>
     * @throws UnexpectedApiResponseFormatException
     * @throws NoAppropriatePackagingFoundException
     * @throws ApiErrorException
     */
    public function findSingleBinData(array $bins, array $items, string $requestHash, string $cacheContext): array
    {
        $endpoint = "$this->apiUrl/packer/findBinSize";

        try {
            $response = $this->client->post($endpoint, [
                'json' => [
                    'bins' => $bins,
                    'items' => $items,
                    'username' => $this->username,
                    'api_key' => $this->apiKey,
                    'params' => [
                        'optimization_mode' => 'bins_number',
                    ],
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $responseBodyString = $response->getBody()->getContents();
                $body = json_decode($responseBodyString, true);

                if ($this->appEnv === 'test' || $this->appEnv === 'dev') {
                    $this->logger->debug('API response body', [
                        ...$this->buildLogContext($items, $requestHash, $cacheContext),
                        'endpoint' => $endpoint,
                        'response_body' => $responseBodyString,
                    ]);
                }

                if (!is_array($body)) {
                    throw new UnexpectedApiResponseFormatException('Response body is not a valid JSON object');
                }

                /** @var array<string, mixed>|null $responseData */
                $responseData = $body['response'] ?? [];
                $diagnostics = $this->extractApiDiagnostics($responseData);
                if ($diagnostics['api_errors'] !== []) {
                    $this->logger->warning('Third party api returned diagnostic fields', [
                        ...$this->buildLogContext($items, $requestHash, $cacheContext),
                        'endpoint' => $endpoint,
                        'api_status' => $diagnostics['api_status'],
                        'api_errors' => $diagnostics['api_errors'],
                    ]);

                    throw new ApiErrorException('Third party API returned error');
                }

                if (
                    !is_array($responseData)
                    || !isset($responseData['bins_packed'])
                    || !is_array($responseData['bins_packed'])
                ) {
                    throw new UnexpectedApiResponseFormatException('Missing or invalid response.bins_packed');
                }

                $binsPacked = $responseData['bins_packed'];
                $notPackedItems = $responseData['not_packed_items'] ?? [];
                if (!is_array($notPackedItems)) {
                    throw new UnexpectedApiResponseFormatException('response.not_packed_items is not an array');
                }

                if (count($binsPacked) !== 1 || $notPackedItems !== []) {
                    $this->logger->warning('API result cannot be represented as a single box.', [
                        ...$this->buildLogContext($items, $requestHash, $cacheContext),
                        'endpoint' => $endpoint,
                        'bins_packed_count' => count($binsPacked),
                        'not_packed_items_count' => count($notPackedItems),
                    ]);
                    throw new NoAppropriatePackagingFoundException();
                }

                if (
                    !isset($binsPacked[0])
                    || !is_array($binsPacked[0])
                    || !isset($binsPacked[0]['bin_data'])
                    || !is_array($binsPacked[0]['bin_data'])
                ) {
                    throw new UnexpectedApiResponseFormatException('Missing or invalid first bin_data in bins_packed');
                }

                /** @var array<string, mixed> $binData */
                $binData = $binsPacked[0]['bin_data'];
                return $binData;
            }

            $responseBody = (string) $response->getBody();
            $decodedBody = json_decode($responseBody, true);
            $decodedResponse = is_array($decodedBody) && isset($decodedBody['response']) ? $decodedBody['response'] : null;
            /** @var array<string, mixed>|null $decodedResponse */
            $diagnostics = $this->extractApiDiagnostics($decodedResponse);
            $this->logger->error('Third party api error', [
                ...$this->buildLogContext($items, $requestHash, $cacheContext),
                'endpoint' => $endpoint,
                'status_code' => $response->getStatusCode(),
                'response_body' => $responseBody,
                'api_status' => $diagnostics['api_status'],
                'api_errors' => $diagnostics['api_errors'],
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Packing service error', [
                ...$this->buildLogContext($items, $requestHash, $cacheContext),
                'endpoint' => $endpoint,
                'exception' => $e,
            ]);
        }

        return [];
    }

    /**
     * @param array<int, array{id: string, w: int, h: int, d: int, q: int, wg: int, vr: int}> $items
     * @return array{request_hash: string, items_count: int, cache_context: string}
     */
    private function buildLogContext(array $items, string $requestHash, string $cacheContext): array
    {
        return [
            'request_hash' => $requestHash,
            'items_count' => count($items),
            'cache_context' => $cacheContext,
        ];
    }

    /**
     * @param array<string, mixed>|null $responseData
     * @return array{api_status: int|string|null, api_errors: array<int|string, mixed>}
     */
    private function extractApiDiagnostics(?array $responseData = null): array
    {
        $status = null;
        $errors = [];

        if (is_array($responseData) && isset($responseData['status']) && (is_int($responseData['status']) || is_string($responseData['status']))) {
            $status = $responseData['status'];
        }
        if (is_array($responseData) && isset($responseData['errors']) && is_array($responseData['errors'])) {
            $errors = $responseData['errors'];
        }

        return [
            'api_status' => $status,
            'api_errors' => $errors,
        ];
    }
}
