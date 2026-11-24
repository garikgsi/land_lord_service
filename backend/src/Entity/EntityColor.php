<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'entity_colors')]
#[ORM\UniqueConstraint(name: 'uniq_entity_colors_target', columns: ['entity_type', 'entity_id'])]
class EntityColor implements VersionedEntity
{
    use HasValidity;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(name: 'entity_type', length: 80)]
        private string $entityType,
        #[ORM\Column(name: 'entity_id')]
        private int $entityId,
        #[ORM\Column(length: 7, nullable: true)]
        private ?string $color = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = null === $color ? null : strtolower($color);
    }
}
