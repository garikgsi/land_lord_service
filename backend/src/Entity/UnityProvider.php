<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'unity_providers')]
#[ORM\Index(name: 'idx_unity_providers_unity_id', columns: ['unity_id'])]
class UnityProvider implements VersionedEntity
{
    use HasDeletion;

    use HasValidity;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'unity_id', nullable: true, onDelete: 'RESTRICT', options: ['comment' => 'Legacy global utility before object-specific suppliers; not used by the application'])]
    private ?Unity $legacyUnity = null;

    public function __construct(
        #[ORM\Column(name: 'name', type: Types::TEXT)]
        private string $name,
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
}
