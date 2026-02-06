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

/** @var array<string, mixed> $doctrine */
$doctrine = require $containerDir . '/doctrine.php';
/** @var array<string, mixed> $repositories */
$repositories = require $containerDir . '/repositories.php';
/** @var array<string, mixed> $logging */
$logging = require $containerDir . '/logging.php';
/** @var array<string, mixed> $http */
$http = require $containerDir . '/http.php';
/** @var array<string, mixed> $services */
$services = require $containerDir . '/services.php';
/** @var array<string, mixed> $controllers */
$controllers = require $containerDir . '/controllers.php';

return array_merge($doctrine, $repositories, $logging, $http, $services, $controllers);
