<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'objects')]
#[ORM\Index(name: 'idx_objects_land_lord_id', columns: ['land_lord_id'])]
class RentalObject
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'land_lord_id', nullable: false, onDelete: 'RESTRICT')]
        private LandLord $landLord,
        #[ORM\Column(name: 'square', type: Types::DECIMAL, columnDefinition: 'NUMERIC')]
        private string $square,
        #[ORM\Column(name: 'name', type: Types::TEXT)]
        private string $name,
        #[ORM\Column(name: 'address', type: Types::TEXT)]
        private string $address,
        #[ORM\Column(name: 'reading_day', type: Types::SMALLINT)]
        private int $readingDay = 25,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLandLord(): LandLord
    {
        return $this->landLord;
    }

    public function setLandLord(LandLord $landLord): void
    {
        $this->landLord = $landLord;
    }

    public function getSquare(): string
    {
        return $this->square;
    }

    public function setSquare(string $square): void
    {
        $this->square = $square;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function getReadingDay(): int
    {
        return $this->readingDay;
    }

    public function setReadingDay(int $readingDay): void
    {
        $this->readingDay = $readingDay;
    }
}
