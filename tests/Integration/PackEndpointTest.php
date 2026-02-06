<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\TestCase\IntegrationTestCase;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;

class PackEndpointTest extends IntegrationTestCase
{
    public function testPackEndpointAcceptsPost(): void
    {
        $app = $this->createApp();
        $request = new ServerRequest('POST', new Uri('http://localhost/pack'),
            ['Content-Type' => 'application/json'], '[{"width": 1, "height": 1, "length": 1, "weight": 1}, {"width": 1, "height": 1, "length": 1, "weight": 1}]');

        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
    }
}
