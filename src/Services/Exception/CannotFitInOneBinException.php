<?php

namespace App\Services\Exception;

class CannotFitInOneBinException extends \Exception
{
    public function __construct(string $message = 'Cannot fit in one bin', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
