<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

const API_RETRY_MAX_ATTEMPTS = 3;
const API_RETRY_BASE_DELAY_MS = 200;
const API_RETRY_MAX_DELAY_MS = 2000;

return [
    Client::class => function () {
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
            ): bool {
                if ($retries >= API_RETRY_MAX_ATTEMPTS) {
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
            static function (int $retries): int {
                $exponentialDelay = API_RETRY_BASE_DELAY_MS * (2 ** max(0, $retries - 1));
                $cappedDelay = min(API_RETRY_MAX_DELAY_MS, $exponentialDelay);
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
