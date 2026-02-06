<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\TestCase\IntegrationTestCase;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;

class PackEndpointTest extends IntegrationTestCase
{
    private const VALID_PRODUCTS = '[{"width": 1, "height": 1, "length": 1, "weight": 1}, {"width": 1, "height": 1, "length": 1, "weight": 1}]';

    public function testPackEndpointAcceptsPost(): void
    {
        $app = $this->createApp();
        $request = new ServerRequest('POST', new Uri('http://localhost/pack'),
            ['Content-Type' => 'application/json'], self::VALID_PRODUCTS);

        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPackReturns200WithValidProducts(): void
    {
        // @todo Valid products array -> 200, JSON with success, box (id, width, height, length, max_weight, dimensions)
        $this->markTestIncomplete('Not implemented');
    }

    public function testPackReturns4xxWithInvalidJson(): void
    {
        // @todo Malformed JSON body -> 4xx, error message in JSON
        $this->markTestIncomplete('Not implemented');
    }

    public function testPackReturns4xxWithEmptyProductArray(): void
    {
        // @todo Empty array [] -> 4xx (empty cart edge case)
        $this->markTestIncomplete('Not implemented');
    }

    public function testPackReturns4xxWithMissingRequiredKeys(): void
    {
        // @todo Product missing width/height/length/weight -> 4xx with clear message
        $this->markTestIncomplete('Not implemented');
    }

    public function testPackReturns422WhenItemsDoNotFit(): void
    {
        // @todo Products exceed all available boxes -> 422, error in JSON body
        $this->markTestIncomplete('Not implemented');
    }

    public function testPackResponseHasContentTypeApplicationJson(): void
    {
        // @todo Assert successful response Content-Type is application/json
        $this->markTestIncomplete('Not implemented');
    }

    public function testPackSameProductsTwiceReturnsSameResult(): void
    {
        // @todo Same request twice -> identical response (cache hit)
        $this->markTestIncomplete('Not implemented');
    }
}
