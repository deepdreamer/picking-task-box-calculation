<?php

use App\Bootstrap\RouteLoader;
use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Build PHP-DI container
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../config/container.php');
$container = $containerBuilder->build();

// Set container for Slim
AppFactory::setContainer($container);
$app = AppFactory::create();

RouteLoader::load($app);

// Add error middleware (for development - shows errors)
$app->addErrorMiddleware(true, true, true);

// Run the app
$app->run();
