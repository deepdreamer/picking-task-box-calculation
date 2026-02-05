<?php

namespace App\Controllers;

use App\Services\InputValidator;
use App\Services\PackingService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PackController
{
    public function __construct(
        private PackingService $service,
        private InputValidator $inputValidator
    ) {
    }
    public function actionPack(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->service->getOptimalBox($this->inputValidator->getProducts($request));
        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }
}
