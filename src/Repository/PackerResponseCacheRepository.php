<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PackerResponseCache;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<PackerResponseCache>
 */
class PackerResponseCacheRepository extends EntityRepository
{
    public function findByRequestHash(string $requestHash): ?PackerResponseCache
    {
        return $this->find($requestHash);
    }
}
