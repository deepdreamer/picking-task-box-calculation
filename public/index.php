<?php

declare(strict_types=1);

use App\Bootstrap\RouteLoader;
use DI\ContainerBuilder;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;

require __DIR__ . '/../bootstrap.php';

// Build PHP-DI container
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../config/container.php');
$container = $containerBuilder->build();

// Set container for Slim
AppFactory::setContainer($container);
$app = AppFactory::create();

RouteLoader::load($app);

$env = $_ENV['APP_ENV'] ?? 'dev';
$isProd = $env === 'prod';

$errorMiddleware = $app->addErrorMiddleware(
    !$isProd,  // displayErrorDetails: true in dev, false in prod
    true,      // logErrors
    true,      // logErrorDetails
    $container->get(LoggerInterface::class)
);


// Run the app
$app->run();
