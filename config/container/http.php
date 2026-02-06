<?php

declare(strict_types=1);

use GuzzleHttp\Client;

return [
    Client::class => function () {
        return new Client();
    },
];
