<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\PackingService;
use PHPUnit\Framework\TestCase;

class PackingServiceTest extends TestCase
{
    /**
     * @todo Inject mocks for: Client, PackagingRepository, PackerResponseCacheRepository,
     *       EntityManagerInterface, LoggerInterface, LocalPackagingCalculator
     */
    public function testGetOptimalBoxReturnsCachedResultWhenCacheHit(): void
    {
        // @todo Cache has entry for request hash -> no API call, returns Packaging from cache
        $this->markTestIncomplete('Not implemented');
    }

    public function testGetOptimalBoxCallsApiWhenCacheMiss(): void
    {
        // @todo No cache entry -> Guzzle post called, result cached, Packaging returned
        $this->markTestIncomplete('Not implemented');
    }

    public function testGetOptimalBoxFallsBackToLocalWhenApiFails(): void
    {
        // @todo GuzzleException or non-200 -> LocalPackagingCalculator used, Packaging returned
        $this->markTestIncomplete('Not implemented');
    }

    public function testGetOptimalBoxThrowsWhenNoPackagingInDatabase(): void
    {
        // @todo PackagingRepository returns empty -> NoPackagingInDatabaseException
        $this->markTestIncomplete('Not implemented');
    }

    public function testGetOptimalBoxThrowsWhenItemsDoNotFitAndApiReturnsEmpty(): void
    {
        // @todo API returns empty bins_packed, local calc cannot fit -> NoAppropriatePackagingFoundException
        $this->markTestIncomplete('Not implemented');
    }

    public function testGetOptimalBoxInvalidatesCacheWhenCachedPackagingNoLongerInDb(): void
    {
        // @todo Cached bin id no longer in PackagingRepository -> cache entry removed, API called again
        $this->markTestIncomplete('Not implemented');
    }

    public function testGetOptimalBoxUsesCanonicalHashForCacheKey(): void
    {
        // @todo Same products in different order -> verify cache key behaviour (order-dependent or not)
        $this->markTestIncomplete('Not implemented');
    }
}
