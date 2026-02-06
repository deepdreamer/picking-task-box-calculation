<?php

namespace App\Services\Exception;

class NonPositiveItemVolumeException extends \Exception
{
    public function __construct(string $message = 'Item volume must be positive', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
