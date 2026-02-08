<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CachedPackagingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CachedPackagingRepository::class)]
class CachedPackaging
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $requestHash {
        get {
            return $this->requestHash;
        }
    }

    public function getRequestHash(): string
    {
        return $this->requestHash;
    }

    #[ORM\Column(type: Types::TEXT)]
    public string $responseBody {
        get {
            return $this->responseBody;
        }
    }

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt {
        get {
            return $this->createdAt;
        }
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __construct(string $requestHash, string $responseBody)
    {
        $this->requestHash = $requestHash;
        $this->responseBody = $responseBody;
        $this->createdAt = new \DateTimeImmutable();
    }
}
