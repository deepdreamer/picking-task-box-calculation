<?php

declare(strict_types=1);

use App\Controllers\PackController;
use App\Services\InputValidator;
use App\Services\OutputFormatter;
use App\Services\PackingService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
    PackController::class => function (ContainerInterface $container) {
        return new PackController(
            $container->get(PackingService::class),
            $container->get(InputValidator::class),
            $container->get(OutputFormatter::class),
            $container->get(LoggerInterface::class),
        );
    },
];
