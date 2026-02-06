<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Controllers\PackController;
use Slim\App;

class RouteLoader
{
    public static function load(App $app): void
    {
        $app->post('/pack', [PackController::class, 'actionPack']);
    }
}
