<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_types')]
class NotificationType
{
    /** @param list<string> $roles */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        private int $id,
        #[ORM\Column(type: Types::TEXT)]
        private string $name,
        #[ORM\Column]
        private int $days,
        #[ORM\Column(type: Types::JSONB)]
        private array $roles,
        #[ORM\Column(name: 'is_active')]
        private bool $isActive,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDays(): int
    {
        return $this->days;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function configure(string $name, int $days, array $roles, bool $isActive): void
    {
        $this->name = trim($name);
        $this->days = $days;
        $this->roles = array_values(array_unique($roles));
        $this->isActive = $isActive;
    }
}
