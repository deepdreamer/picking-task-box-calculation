<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ServerRequestInterface;

class InputValidator
{
    private const REQUIRED_KEYS = ['width', 'height', 'length', 'weight'];

    /** @var list<array{width: int, height: int, length: int, weight: int}> */
    private array $products = [];

    /**
     * @throws \InvalidArgumentException
     */
    public function validateProducts(ServerRequestInterface $request): bool
    {
        $content = $request->getBody()->getContents();
        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Products must be a JSON array.');
        }

        $products = [];
        foreach ($decoded as $index => $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException("Product at index {$index} must be an array.");
            }

            foreach (self::REQUIRED_KEYS as $key) {
                if (!array_key_exists($key, $item)) {
                    throw new \InvalidArgumentException("Product at index {$index} is missing key '{$key}'.");
                }
                if (!is_int($item[$key])) {
                    throw new \InvalidArgumentException("Product at index {$index}, key '{$key}' must be an integer.");
                }
            }

            $extra = array_diff_key($item, array_flip(self::REQUIRED_KEYS));
            if ($extra !== []) {
                throw new \InvalidArgumentException(
                    "Product at index {$index} has unexpected keys: " . implode(', ', array_keys($extra))
                );
            }

            $products[] = [
                'width' => $item['width'],
                'height' => $item['height'],
                'length' => $item['length'],
                'weight' => $item['weight'],
            ];
        }

        $this->products = $products;
        return true;
    }


    /**
     * @return list<array{width: int, height: int, length: int, weight: int}>
     */
    public function getProducts(ServerRequestInterface $request): array
    {
        $this->validateProducts($request);

        return $this->products;
    }
}
