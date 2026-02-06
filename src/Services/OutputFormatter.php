<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Packaging;

class OutputFormatter
{
    /**
     * Formats the optimal packaging as a structured array for JSON output.
     *
     * @return array{success: true, box: array{id: int|null, width: float, height: float, length: float, max_weight: float, dimensions: string}}
     */
    public function format(Packaging $packaging): array
    {
        return [
            'success' => true,
            'box' => [
                'id' => $packaging->id,
                'width' => $packaging->width,
                'height' => $packaging->height,
                'length' => $packaging->length,
                'max_weight' => $packaging->maxWeight,
                'dimensions' => sprintf(
                    '%.2f × %.2f × %.2f cm',
                    $packaging->width,
                    $packaging->height,
                    $packaging->length
                ),
            ],
        ];
    }

    /**
     * Returns the formatted result as pretty-printed JSON string.
     */
    public function toJson(Packaging $packaging, int $options = 0): string
    {
        $options |= JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;

        $json = json_encode($this->format($packaging), $options);
        return $json !== false ? $json : '{}';
    }
}
