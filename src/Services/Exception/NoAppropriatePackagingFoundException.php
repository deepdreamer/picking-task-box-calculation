<?php

declare(strict_types=1);

namespace App\Services\Exception;

class NoAppropriatePackagingFoundException extends \Exception
{
    public function __construct(
        string $message = 'No appropriate packing found',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
