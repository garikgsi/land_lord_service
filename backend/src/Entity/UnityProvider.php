<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'unity_providers')]
#[ORM\Index(name: 'idx_unity_providers_unity_id', columns: ['unity_id'])]
class UnityProvider
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'unity_id', nullable: false, onDelete: 'RESTRICT')]
        private Unity $unity,
        #[ORM\Column(name: 'name', type: Types::TEXT)]
        private string $name,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUnity(): Unity
    {
        return $this->unity;
    }

    public function setUnity(Unity $unity): void
    {
        $this->unity = $unity;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
