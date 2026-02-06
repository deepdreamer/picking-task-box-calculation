<?php

namespace App\Entity;

use App\Repository\PackerResponseCacheRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PackerResponseCacheRepository::class)]
#[ORM\Table(name: 'packer_response_cache')]
class PackerResponseCache
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $requestHash {
        get {
            return $this->requestHash;
        }
    }

    #[ORM\Column(type: Types::TEXT)]
    private string $responseBody {
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

    public function __construct(string $requestHash, string $responseBody)
    {
        $this->requestHash = $requestHash;
        $this->responseBody = $responseBody;
        $this->createdAt = new \DateTimeImmutable();
    }
}
