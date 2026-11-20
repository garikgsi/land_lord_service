<?php

declare(strict_types=1);

namespace App\Entity;

/** An entity with an optional user-selected display colour. */
interface Colorable
{
    public function getColor(): ?string;

    public function setColor(?string $color): void;
}
