<?php

namespace App\Entity;

use App\Repository\PackagingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a box available in the warehouse.
 *
 * Warehouse workers pack a set of products for a given order into one of these boxes.
 */
#[ORM\Entity(repositoryClass: PackagingRepository::class)]
class Packaging
{

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(type: Types::FLOAT)]
    public float $width {
        get {
            return $this->width;
        }
    }

    #[ORM\Column(type: Types::FLOAT)]
    public float $height {
        get {
            return $this->height;
        }
    }

    #[ORM\Column(type: Types::FLOAT)]
    public float $length {
        get {
            return $this->length;
        }
    }

    #[ORM\Column(type: Types::FLOAT)]
    public float $maxWeight {
        get {
            return $this->maxWeight;
        }
    }

    public function __construct(float $width, float $height, float $length, float $maxWeight)
    {
        $this->width = $width;
        $this->height = $height;
        $this->length = $length;
        $this->maxWeight = $maxWeight;
    }

}
