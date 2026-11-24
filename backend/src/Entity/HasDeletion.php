<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait HasDeletion
{
    #[ORM\Column(name: 'is_deleted', options: ['default' => false])]
    private bool $isDeleted = false;

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function markDeleted(): void
    {
        $this->isDeleted = true;
    }
}
