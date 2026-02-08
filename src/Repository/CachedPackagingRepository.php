<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CachedPackaging;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<CachedPackaging>
 */
class CachedPackagingRepository extends EntityRepository
{
    public function findByRequestHash(string $requestHash): ?CachedPackaging
    {
        return $this->find($requestHash);
    }
}
