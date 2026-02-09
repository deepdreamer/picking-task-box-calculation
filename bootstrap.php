<?php

/**
 * Central bootstrap: loads autoloader and populates env from .env
 * Include this first in any entry point (web, CLI, tests)
 */

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

$dotenv = new Dotenv();

$globalEnvFile = __DIR__ . '/.env';
if (file_exists($globalEnvFile)) {
    $dotenv->load($globalEnvFile);
}

/** @var string|false|null $appEnv */
$appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV');
if ($appEnv === null || $appEnv === false || $appEnv === '') {
    throw new RuntimeException('Application environment parameter not set (prod, test, dev).');
}

if ($appEnv !== 'prod' && $appEnv !== 'test' && $appEnv !== 'dev') {
    throw new RuntimeException('Wrong application environment parameter (only: prod, test, dev).');
}

$environmentFile = __DIR__ . '/.env.' . $appEnv;

if (file_exists($environmentFile)) {
    $dotenv->load($environmentFile);
}
