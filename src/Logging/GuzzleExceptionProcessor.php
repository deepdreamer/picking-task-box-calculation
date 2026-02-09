<?php

declare(strict_types=1);

namespace App\Logging;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Monolog\LogRecord;

final class GuzzleExceptionProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $exception = $record->context['exception'] ?? null;
        if (!$exception instanceof GuzzleException) {
            return $record;
        }

        $context = $record->context;
        $context['exception_class'] = $exception::class;
        $context['exception_message'] = $exception->getMessage();
        $context['exception_code'] = $exception->getCode();
        $context['exception_file'] = $exception->getFile();
        $context['exception_line'] = $exception->getLine();

        if ($exception instanceof RequestException) {
            $request = $exception->getRequest();
            $context['request_method'] = $request->getMethod();
            $context['request_uri'] = (string) $request->getUri();

            $response = $exception->getResponse();
            if ($response !== null) {
                $context['response_status_code'] = $response->getStatusCode();
                $context['response_body'] = self::truncateForLog((string) $response->getBody());
            }

            $handlerContext = $exception->getHandlerContext();
            if ($handlerContext !== []) {
                $context['handler_context'] = $handlerContext;
            }
        }

        return $record->with(context: $context);
    }

    private static function truncateForLog(string $value, int $maxLength = 2000): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength) . '...';
    }
}
