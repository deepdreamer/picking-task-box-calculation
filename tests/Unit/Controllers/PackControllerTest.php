<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controllers;

use App\Controllers\PackController;
use App\Services\Exception\InputValidationException;
use PHPUnit\Framework\TestCase;

class PackControllerTest extends TestCase
{
    /**
     * @todo Inject mocks: PackingService, InputValidator, OutputFormatter, LoggerInterface
     *       Use GuzzleHttp\Psr7\ServerRequest / Response for request/response objects
     */
    public function testActionPackReturns200WithValidResult(): void
    {
        // @todo Service returns Packaging -> 200, JSON body with box data
        $this->markTestIncomplete('Not implemented');
    }

    public function testActionPackReturns422WhenCannotFitInOneBin(): void
    {
        // @todo PackingService throws CannotFitInOneBinException -> 422, JSON error message
        $this->markTestIncomplete('Not implemented');
    }

    public function testActionPackReturns422WhenNoAppropriatePackagingFound(): void
    {
        // @todo PackingService throws NoAppropriatePackagingFoundException -> 422, JSON error message
        $this->markTestIncomplete('Not implemented');
    }

    public function testActionPackReturns400WhenInputValidationFails(): void
    {
        // @todo InputValidator throws InputValidationException -> 400, JSON error message
        // Note: controller must catch InputValidationException (may need implementation)
        $this->markTestIncomplete('Not implemented');
    }

    public function testActionPackSetsContentTypeApplicationJson(): void
    {
        // @todo Response has Content-Type: application/json header
        $this->markTestIncomplete('Not implemented');
    }

    public function testActionPackLogsErrorOnPackagingFailure(): void
    {
        // @todo Logger->error called when CannotFitInOneBinException or NoAppropriatePackagingFoundException
        $this->markTestIncomplete('Not implemented');
    }
}
