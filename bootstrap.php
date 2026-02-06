<?php

/**
 * Central bootstrap: loads autoloader and populates env from .env
 * Include this first in any entry point (web, CLI, tests)
 */

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    new Dotenv()->load($envFile);
}
