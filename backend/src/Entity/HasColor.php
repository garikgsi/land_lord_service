<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait HasColor
{
    #[ORM\Column(name: 'color', length: 7, nullable: true)]
    private ?string $color = null;

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = null === $color ? null : strtolower($color);
    }
}
