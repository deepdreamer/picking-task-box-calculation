<?php

use App\Controllers\PackController;
use App\Services\InputValidator;
use App\Services\OutputFormatter;
use App\Services\PackingService;
use Psr\Container\ContainerInterface;

return [
    PackController::class => function (ContainerInterface $container) {
        return new PackController(
            $container->get(PackingService::class),
            $container->get(InputValidator::class),
            $container->get(OutputFormatter::class),
        );
    },
];
