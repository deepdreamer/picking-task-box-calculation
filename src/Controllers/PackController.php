<?php

namespace App\Controllers;

use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp;
use Psr\Http\Message\ServerRequestInterface as Request;

class PackController
{
    private EntityManager $entityManager;
    private GuzzleHttp\Client $client;

    public function __construct(
        EntityManager $entityManager,
        GuzzleHttp\Client $client
    ) {
        $this->entityManager = $entityManager;
        $this->client = $client;
    }
    public function actionPack(Request $request, ResponseInterface $response): ResponseInterface
    {
        // Example: How to use Guzzle to send HTTP requests

        // Method 1: Simple GET request
        // $response = $this->client->get('https://api.example.com/data');

        // Method 2: POST request with JSON body
        // $response = $this->client->post('https://api.example.com/endpoint', [
        //     'json' => ['key' => 'value']
        // ]);

        // Method 3: POST request with custom headers
        // $response = $this->client->post('https://api.example.com/endpoint', [
        //     'headers' => ['Content-Type' => 'application/json'],
        //     'body' => json_encode(['data' => 'value'])
        // ]);

        // Method 4: Using request() method with full control
        // $response = $this->client->request('POST', 'https://api.example.com/endpoint', [
        //     'headers' => ['Authorization' => 'Bearer token'],
        //     'json' => ['key' => 'value'],
        //     'timeout' => 30
        // ]);

        // Example for the 3D Bin Packing API (commented in your code)
        // $response = $this->client->post('https://global-api.3dbinpacking.com/packer/findBinSize', [
        //     'headers' => [
        //         'Content-Type' => 'application/json',
        //     ],
        //     'json' => json_decode($request->getBody()->getContents(), true)
        // ]);

        // Access response data:
        // $statusCode = $response->getStatusCode();
        // $body = $response->getBody()->getContents();
        // $data = json_decode($body, true);

        var_dump($request->getBody()->getContents());die();

//        'https://global-api.3dbinpacking.com/packer/findBinSize'
//        pahis96267@aixind.com
        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }
}
