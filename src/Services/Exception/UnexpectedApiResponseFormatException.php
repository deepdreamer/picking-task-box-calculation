<?php

declare(strict_types=1);

namespace App\Services\Exception;

class UnexpectedApiResponseFormatException extends \Exception
{
    public function __construct(
        string $message = 'API response structure does not match expected format',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
