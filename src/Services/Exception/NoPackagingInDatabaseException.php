<?php

declare(strict_types=1);

namespace App\Services\Exception;

class NoPackagingInDatabaseException extends \Exception
{
    public function __construct(string $message = 'No packaging in database', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
