<?php

use App\Bootstrap\RouteLoader;
use DI\ContainerBuilder;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

// Build PHP-DI container
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/config/container.php');
$container = $containerBuilder->build();

// Set container for Slim
AppFactory::setContainer($container);
$app = AppFactory::create();

RouteLoader::load($app);

$request = new ServerRequest('POST', new Uri('http://localhost/pack'), ['Content-Type' => 'application/json'], $argv[1] ?? '{}');
$response = $app->handle($request);

echo "<<< In:\n" . Message::toString($request) . "\n\n";
echo ">>> Out:\n" . Message::toString($response) . "\n\n";
