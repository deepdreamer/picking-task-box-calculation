<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\OutputFormatter;
use PHPUnit\Framework\TestCase;

class OutputFormatterTest extends TestCase
{
    private OutputFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new OutputFormatter();
    }

    public function testFormatReturnsExpectedStructure(): void
    {
        // @todo Assert format() returns array with success, box (id, width, height, length, max_weight, dimensions)
        $this->markTestIncomplete('Not implemented');
    }

    public function testFormatIncludesCorrectDimensionsString(): void
    {
        // @todo Assert dimensions string format (e.g. "10.00 × 20.00 × 30.00 cm")
        $this->markTestIncomplete('Not implemented');
    }

    public function testToJsonReturnsValidJson(): void
    {
        // @todo Assert toJson() returns parseable JSON string
        $this->markTestIncomplete('Not implemented');
    }

    public function testFormatHandlesNullPackagingId(): void
    {
        // @todo Packaging with null id - assert format handles gracefully
        $this->markTestIncomplete('Not implemented');
    }
}
