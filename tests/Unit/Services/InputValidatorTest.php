<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\InputValidator;
use App\Services\Exception\InputValidationException;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class InputValidatorTest extends TestCase
{
    private InputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new InputValidator();
    }

    public function testGetProductsReturnsValidProductsArray(): void
    {
        // @todo Valid JSON array of products with width, height, length, weight
        $this->markTestIncomplete('Not implemented');
    }

    public function testGetProductsThrowsWhenInputIsNotJsonArray(): void
    {
        // @todo Invalid JSON or non-array (e.g. object) throws InputValidationException
        $this->markTestIncomplete('Not implemented');
    }

    public function testGetProductsThrowsWhenProductIsMissingRequiredKey(): void
    {
        // @todo Product missing width/height/length/weight throws with clear message
        $this->markTestIncomplete('Not implemented');
    }

    public function testGetProductsThrowsWhenValueIsNotNumeric(): void
    {
        // @todo Product with string/boolean dimension throws InputValidationException
        $this->markTestIncomplete('Not implemented');
    }

    public function testGetProductsThrowsWhenProductHasUnexpectedKeys(): void
    {
        // @todo Product with extra keys (e.g. "id") throws per current strict validation
        $this->markTestIncomplete('Not implemented');
    }

    public function testGetProductsAcceptsEmptyArray(): void
    {
        // @todo Empty array [] - validate current behaviour (passes or throws)
        $this->markTestIncomplete('Not implemented');
    }

    public function testGetProductsAcceptsSingleProduct(): void
    {
        // @todo Single product returns single-element array
        $this->markTestIncomplete('Not implemented');
    }
}
