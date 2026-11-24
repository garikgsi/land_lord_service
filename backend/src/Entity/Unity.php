<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'unities')]
class Unity implements VersionedEntity
{
    use HasDeletion;

    use HasValidity;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(name: 'name', type: Types::TEXT)]
        private string $name,
        #[ORM\Column(name: 'use_meter', type: Types::BOOLEAN)]
        private bool $useMeter,
        #[ORM\Column(name: 'unit', type: Types::TEXT, nullable: true)]
        private ?string $unit = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getUseMeter(): bool
    {
        return $this->useMeter;
    }

    public function setUseMeter(bool $useMeter): void
    {
        $this->useMeter = $useMeter;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): void
    {
        $this->unit = $unit;
    }
}
