<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Exception\CannotFitInOneBinException;
use App\Services\Exception\InputValidationException;
use App\Services\Exception\NoAppropriatePackagingFoundException;
use App\Services\Exception\NoPackagingInDatabaseException;
use App\Services\Exception\NonPositiveItemVolumeException;
use App\Services\Exception\NonPositiveItemWeightException;
use App\Services\Exception\TotalItemsDimensionsException;
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

    public function actionPack(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $optimalBox = $this->service->getOptimalBox($this->inputValidator->getProducts($request));
            $response->getBody()->write($this->outputFormatter->toJson($optimalBox));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        } catch (InputValidationException $e) {
            $body = json_encode(['error' => $e->getMessage()]);
            $response->getBody()->write($body !== false ? $body : '{}');
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        } catch (
            TotalItemsDimensionsException // @todo: this can be removed, if we enforce positive numeric values, over engineering
            | NonPositiveItemVolumeException // @todo: this should be taken care of by input validator
            | NonPositiveItemWeightException // @todo: this should be taken care of by input validator
            | NoPackagingInDatabaseException $e
        ) {
            $this->logger->error('Domain validation or packaging error', ['exception' => $e]);
            $body = json_encode(['error' => $e->getMessage()]);
            $response->getBody()->write($body !== false ? $body : '{}');
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        } catch (CannotFitInOneBinException | NoAppropriatePackagingFoundException $e) {
            $this->logger->error('Failed to find appropriate packaging for products', ['exception' => $e]);
            $body = json_encode(['error' => $e->getMessage()]);
            $response->getBody()->write($body !== false ? $body : '{}');
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }
    }
}
