<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'spaces')]
#[ORM\Index(name: 'idx_spaces_object_id', columns: ['object_id'])]
class Space
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'object_id', nullable: false, onDelete: 'RESTRICT')]
        private RentalObject $object,
        #[ORM\Column(name: 'name', type: Types::TEXT)]
        private string $name,
        #[ORM\Column(name: 'square', type: Types::DECIMAL, columnDefinition: 'NUMERIC')]
        private string $square,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getObject(): RentalObject
    {
        return $this->object;
    }

    public function setObject(RentalObject $object): void
    {
        $this->object = $object;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSquare(): string
    {
        return $this->square;
    }

    public function setSquare(string $square): void
    {
        $this->square = $square;
    }
}
