<?php

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class => function () {
        $env = $_ENV['APP_ENV'] ?? 'dev';
        $isProd = $env === 'prod';

        $logger = new Logger('packing-service');
        $logFile = $isProd
            ? __DIR__ . '/../../logs/production.log'
            : __DIR__ . '/../../logs/dev.log';

        $minLevel = $isProd ? Level::Error : Level::Debug;
        $logger->pushHandler(new StreamHandler($logFile, $minLevel));

        return $logger;
    },
];
