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
        /** @var PackingService $service */
        $service = $container->get(PackingService::class);
        /** @var InputValidator $inputValidator */
        $inputValidator = $container->get(InputValidator::class);
        /** @var OutputFormatter $outputFormatter */
        $outputFormatter = $container->get(OutputFormatter::class);
        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        return new PackController($service, $inputValidator, $outputFormatter, $logger);
    },
];
