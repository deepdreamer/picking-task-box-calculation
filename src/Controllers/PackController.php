<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Exception\CannotFitInOneBinException;
use App\Services\Exception\NoAppropriatePackagingFoundException;
use App\Services\InputValidator;
use App\Services\OutputFormatter;
use App\Services\PackingService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class PackController
{
    public function __construct(
        private PackingService $service,
        private InputValidator $inputValidator,
        private OutputFormatter $outputFormatter,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @throws \Exception
     */
    public function actionPack(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $optimalBox = $this->service->getOptimalBox($this->inputValidator->getProducts($request));
            $response->getBody()->write($this->outputFormatter->toJson($optimalBox));
        } catch (CannotFitInOneBinException | NoAppropriatePackagingFoundException $e) {
            $this->logger->error('Failed to find appropriate packaging for products', ['exception' => $e]);
            $body = json_encode(['error' => $e->getMessage()]);
            $response->getBody()->write($body);
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }
}
