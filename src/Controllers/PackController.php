<?php

namespace App\Controllers;

use App\Services\InputValidator;
use App\Services\OutputFormatter;
use App\Services\PackingService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PackController
{
    public function __construct(
        private PackingService $service,
        private InputValidator $inputValidator,
        private OutputFormatter $outputFormatter
    ) {
    }

    /**
     * @throws \Exception
     */
    public function actionPack(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $optimalBox = $this->service->getOptimalBox($this->inputValidator->getProducts($request));
        $response->getBody()->write($this->outputFormatter->toJson($optimalBox));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }
}
