<?php

declare(strict_types=1);

return [
    // Database connection parameters for Doctrine DBAL
    'connection_params' => [
        'driver' => (($_ENV['DB_DRIVER'] ?? 'pdo_mysql') === 'mysqli') ? 'mysqli' : 'pdo_mysql',
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'host' => $_ENV['DB_HOST'] ?? 'shipmonk-packing-mysql',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? 'secret',
        'dbname' => $_ENV['DB_NAME'] ?? 'packing',
    ],

    'doctrine' => [
        'paths' => [
            __DIR__ . '/../src/Entity',
        ],
        'is_dev_mode' => ($_ENV['APP_ENV'] ?? 'prod') !== 'prod',
    ],
];
