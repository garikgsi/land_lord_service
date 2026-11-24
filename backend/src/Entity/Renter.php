<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'renters')]
#[ORM\Index(name: 'idx_renters_ownership_id', columns: ['ownership_id'])]
#[ORM\UniqueConstraint(name: 'uq_renters_inn', columns: ['inn'])]
class Renter implements VersionedEntity
{
    use HasDeletion;

    use HasValidity;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'ownership_id', nullable: false, onDelete: 'RESTRICT')]
        private Ownership $ownership,
        #[ORM\Column(name: 'name', type: Types::TEXT)]
        private string $name,
        #[ORM\Column(name: 'inn', type: Types::TEXT, nullable: true)]
        private ?string $inn = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwnership(): Ownership
    {
        return $this->ownership;
    }

    public function setOwnership(Ownership $ownership): void
    {
        $this->ownership = $ownership;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getInn(): ?string
    {
        return $this->inn;
    }

    public function setInn(?string $inn): void
    {
        $this->inn = $inn;
    }
}
