<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Exception\InputValidationException;
use Psr\Http\Message\ServerRequestInterface;

class InputValidator
{
    private const array REQUIRED_KEYS = ['width', 'height', 'length', 'weight', 'id'];

    /** @var list<array{width: float, height: float, length: float, weight: float}> */
    private array $products = [];

    /**
     * @throws InputValidationException
     */
    public function validateProducts(ServerRequestInterface $request): bool
    {
        $content = $request->getBody()->getContents();
        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new InputValidationException('Products must be a JSON array.');
        }

        if ($decoded === []) {
            throw new InputValidationException('Product list must not be empty.');
        }

        $products = [];
        if (!array_key_exists('products', $decoded) || !is_array($decoded['products'])) {
            throw new InputValidationException('Products must be a JSON array.');
        }
        $productsInRequest = $decoded['products'];
        if ($productsInRequest === []) {
            throw new InputValidationException('Product list must not be empty.');
        }


        foreach ($productsInRequest as $index => $item) {
            if (!is_array($item)) {
                throw new InputValidationException("Product at index {$index} must be an array.");
            }

            foreach (self::REQUIRED_KEYS as $key) {
                if (!array_key_exists($key, $item)) {
                    throw new InputValidationException("Product at index {$index} is missing key '{$key}'.");
                }
                if (!is_numeric($item[$key])) {
                    throw new InputValidationException("Product at index {$index}, key '{$key}' must be an number.");
                }
            }

            $extra = array_diff_key($item, array_flip(self::REQUIRED_KEYS));
            if ($extra !== []) {
                throw new InputValidationException(
                    "Product at index {$index} has unexpected keys: " . implode(', ', array_keys($extra))
                );
            }

            $width = $this->enforceNumericValueConvertToFloat($item['width']);
            $height = $this->enforceNumericValueConvertToFloat($item['height']);
            $length = $this->enforceNumericValueConvertToFloat($item['length']);
            $weight = $this->enforceNumericValueConvertToFloat($item['weight']);


            if ($width <= 0 || $height <= 0 || $length <= 0) {
                throw new InputValidationException(
                    "Product at index {$index}: width, height, and length must be positive."
                );
            }
            if ($weight <= 0) {
                throw new InputValidationException(
                    "Product at index {$index}: weight must be positive."
                );
            }
            $products[] = [
                'width' => $width,
                'height' => $height,
                'length' => $length,
                'weight' => $weight,
            ];
        }

        $this->products = $products;
        return true;
    }


    /**
     * @return list<array{width: float, height: float, length: float, weight: float}>
     * @throws InputValidationException
     */
    public function getProducts(ServerRequestInterface $request): array
    {
        $this->validateProducts($request);

        return $this->products;
    }

    /**
     * @throws InputValidationException
     */
    private function enforceNumericValueConvertToFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }
        throw new InputValidationException('Expected numeric value');
    }
}
