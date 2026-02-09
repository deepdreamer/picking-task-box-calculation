<?php

/**
 * PHPUnit bootstrap: loads project bootstrap (autoload + .env)
 */

declare(strict_types=1);

$_ENV['APP_ENV'] = 'test';
$_SERVER['APP_ENV'] = 'test';
putenv('APP_ENV=test');

$_ENV['DB_NAME'] = 'packing_test';
$_SERVER['DB_NAME'] = 'packing_test';
putenv('DB_NAME=packing_test');

require __DIR__ . '/../bootstrap.php';
