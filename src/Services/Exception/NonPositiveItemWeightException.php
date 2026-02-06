<?php

declare(strict_types=1);

namespace App\Services\Exception;

class NonPositiveItemWeightException extends \Exception
{
    public function __construct(
        string $message = 'Item weight must be positive',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
