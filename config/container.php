<?php

declare(strict_types=1);

/**
 * Application container definitions.
 *
 * Definitions are split by concern for easier navigation:
 *
 * - container/doctrine.php   — DBAL Connection, EntityManager
 * - container/repositories.php — Doctrine repositories
 * - container/logging.php   — PSR-3 Logger
 * - container/http.php      — HTTP client (Guzzle)
 * - container/services.php — Application services (validators, domain services)
 * - container/controllers.php — HTTP controllers
 */

$containerDir = __DIR__ . '/container';

return array_merge(
    require $containerDir . '/doctrine.php',
    require $containerDir . '/repositories.php',
    require $containerDir . '/logging.php',
    require $containerDir . '/http.php',
    require $containerDir . '/services.php',
    require $containerDir . '/controllers.php'
);
