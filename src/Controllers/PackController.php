<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Exception\InputValidationException;
use App\Services\Exception\NoAppropriatePackagingFoundException;
use App\Services\Exception\NoPackagingInDatabaseException;
use App\Services\InputValidator;
use App\Services\OutputFormatter;
use App\Services\PackingService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

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
            return $this->sendJsonErrorResponse($response, $e, 400);
        } catch (NoPackagingInDatabaseException $e) {
            return $this->sendJsonErrorResponse($response, $e, 422, 'Domain validation or packaging error');
        } catch (NoAppropriatePackagingFoundException $e) {
            return $this->sendJsonErrorResponse($response, $e, 422, 'Failed to find appropriate packaging for products');
        }
    }

    private function sendJsonErrorResponse(ResponseInterface $response, Throwable $e, int $httpErrorCode, ?string $errorMsgToLog = null): ResponseInterface
    {
        if ($errorMsgToLog !== null) {
            $this->logger->error($errorMsgToLog, ['exception' => $e]);
        }

        $body = json_encode(['error' => $e->getMessage()]);
        $response->getBody()->write($body !== false ? $body : '{}');

        return $response->withStatus($httpErrorCode)->withHeader('Content-Type', 'application/json');
    }
}
