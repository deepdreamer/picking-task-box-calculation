<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

return [
    Client::class => function () {
        $toPositiveInt = static function (mixed $value, int $default): int {
            if (!is_numeric($value)) {
                return $default;
            }

            $parsed = (int) $value;
            return $parsed > 0 ? $parsed : $default;
        };

        $retryMaxAttempts = $toPositiveInt($_ENV['API_RETRY_MAX_ATTEMPTS'] ?? null, 3);
        $retryBaseDelayMs = $toPositiveInt($_ENV['API_RETRY_BASE_DELAY_MS'] ?? null, 200);
        $retryMaxDelayMs = $toPositiveInt($_ENV['API_RETRY_MAX_DELAY_MS'] ?? null, 2000);

        $handlerStack = HandlerStack::create();
        $handlerStack->push(Middleware::retry(
            /**
             * @param int $retries
             * @param RequestInterface $request
             * @param ResponseInterface|null $response
             * @param \Throwable|null $exception
             */
            static function (
                int $retries,
                RequestInterface $request,
                ?ResponseInterface $response = null,
                ?\Throwable $exception = null
            ) use ($retryMaxAttempts): bool {
                if ($retries >= $retryMaxAttempts) {
                    return false;
                }

                if ($exception instanceof ConnectException) {
                    return true;
                }

                if ($exception instanceof RequestException && $exception->getHandlerContext() !== []) {
                    $context = $exception->getHandlerContext();
                    if (($context['errno'] ?? 0) === CURLE_OPERATION_TIMEDOUT) {
                        return true;
                    }
                }

                if ($response !== null) {
                    $statusCode = $response->getStatusCode();
                    if ($statusCode === 400 || $statusCode === 404) {
                        return false;
                    }

                    return $statusCode === 429 || ($statusCode >= 500 && $statusCode <= 599);
                }

                return false;
            },
            static function (int $retries) use ($retryBaseDelayMs, $retryMaxDelayMs): int {
                $exponentialDelay = $retryBaseDelayMs * (2 ** max(0, $retries - 1));
                $cappedDelay = min($retryMaxDelayMs, $exponentialDelay);
                $jitterMs = random_int(0, 100);

                return $cappedDelay + $jitterMs;
            }
        ));

        return new Client([
            'connect_timeout' => 5,
            'timeout' => 15,
            'handler' => $handlerStack,
        ]);
    },
];
