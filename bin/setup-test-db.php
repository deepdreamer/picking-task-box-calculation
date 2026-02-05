<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;

require __DIR__ . '/../bootstrap.php';

// Derive test DB name from .env's DB_NAME
$baseDbName = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'packing';
$testDbName = $baseDbName . '_test';

$_ENV['DB_NAME'] = $testDbName;
$_ENV['APP_ENV'] = 'test';
putenv("DB_NAME=$testDbName");
putenv('APP_ENV=test');

// Step 1: Create test database
$dbConfig = require __DIR__ . '/../config/database.php';
$params = $dbConfig['connection_params'];
$bootstrapParams = $params;
$bootstrapParams['dbname'] = 'mysql'; // need existing database

$bootstrapConn = DriverManager::getConnection($bootstrapParams);
$bootstrapConn->executeStatement("CREATE DATABASE IF NOT EXISTS `{$testDbName}`");

// Step 2: Create schema and seed data
$projectRoot = __DIR__ . '/..';
$seedSql = file_get_contents($projectRoot . '/data/packaging-data-test.sql');
$command = sprintf(
    'cd %s && php bin/doctrine orm:schema-tool:create && php bin/doctrine dbal:run-sql %s',
    escapeshellarg($projectRoot),
    escapeshellarg($seedSql)
);

passthru($command, $exitCode);
exit($exitCode);
