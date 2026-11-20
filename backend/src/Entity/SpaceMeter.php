<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'space_meters')]
#[ORM\Index(name: 'idx_space_meters_space_id', columns: ['space_id'])]
#[ORM\UniqueConstraint(name: 'uq_space_meters_meter', columns: ['meter_id'])]
class SpaceMeter
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'space_id', nullable: false, onDelete: 'RESTRICT')]
        private Space $space,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'meter_id', nullable: false, onDelete: 'RESTRICT')]
        private Meter $meter,
        #[ORM\Column(name: 'comment', type: Types::TEXT, nullable: true)]
        private ?string $comment = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSpace(): Space
    {
        return $this->space;
    }

    public function setSpace(Space $space): void
    {
        $this->space = $space;
    }

    public function getMeter(): Meter
    {
        return $this->meter;
    }

    public function setMeter(Meter $meter): void
    {
        $this->meter = $meter;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }
}
