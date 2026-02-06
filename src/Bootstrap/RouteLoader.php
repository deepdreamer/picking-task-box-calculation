<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Controllers\PackController;
use Psr\Container\ContainerInterface;
use Slim\App;

class RouteLoader
{
    /**
     * @param App<ContainerInterface|null> $app
     */
    public static function load(App $app): void
    {
        $app->post('/pack', [PackController::class, 'actionPack']);
    }
}
