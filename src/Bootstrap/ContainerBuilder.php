<?php

declare(strict_types=1);

namespace App\Bootstrap;

use DI\ContainerBuilder as DIContainerBuilder;
use Psr\Container\ContainerInterface;

class ContainerBuilder
{
    public static function build(): ContainerInterface
    {
        $builder = new DIContainerBuilder();

        // Load container definitions
        $builder->addDefinitions(__DIR__ . '/../../config/container.php');

        return $builder->build();
    }
}
