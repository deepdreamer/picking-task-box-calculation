<?php

use GuzzleHttp\Client;

return [
    Client::class => function () {
        return new Client();
    },
];
