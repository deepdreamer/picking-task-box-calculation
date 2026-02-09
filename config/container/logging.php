<?php

declare(strict_types=1);

use App\Logging\GuzzleExceptionProcessor;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class => function () {
        $env = $_ENV['APP_ENV'] ?? 'dev';
        $env = is_string($env) ? strtolower($env) : 'dev';

        $logger = new Logger('packing-service');
        $logFile = match ($env) {
            'prod' => __DIR__ . '/../../logs/prod.log',
            'test' => __DIR__ . '/../../logs/test.log',
            default => __DIR__ . '/../../logs/dev.log',
        };

        $minLevel = $env === 'prod' ? Level::Error : Level::Debug;
        $logger->pushHandler(new StreamHandler($logFile, $minLevel));
        $logger->pushProcessor(new GuzzleExceptionProcessor());

        return $logger;
    },
];
