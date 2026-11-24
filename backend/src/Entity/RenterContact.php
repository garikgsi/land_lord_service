<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'renter_contacts')]
#[ORM\Index(name: 'idx_renter_contacts_renter_id', columns: ['renter_id'])]
class RenterContact implements VersionedEntity
{
    use HasDeletion;

    use HasValidity;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'renter_id', nullable: false, onDelete: 'RESTRICT')]
        private Renter $renter,
        #[ORM\Column(name: 'name', type: Types::TEXT)]
        private string $name,
        #[ORM\Column(name: 'last_name', type: Types::TEXT, nullable: true)]
        private ?string $lastName = null,
        #[ORM\Column(name: 'patronymic', type: Types::TEXT, nullable: true)]
        private ?string $patronymic = null,
        #[ORM\Column(name: 'phone', type: Types::TEXT, nullable: true)]
        private ?string $phone = null,
        #[ORM\Column(name: 'email', type: Types::TEXT, nullable: true)]
        private ?string $email = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRenter(): Renter
    {
        return $this->renter;
    }

    public function setRenter(Renter $renter): void
    {
        $this->renter = $renter;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getPatronymic(): ?string
    {
        return $this->patronymic;
    }

    public function setPatronymic(?string $patronymic): void
    {
        $this->patronymic = $patronymic;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }
}
