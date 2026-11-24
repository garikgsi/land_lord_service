<?php

declare(strict_types=1);

namespace App\Entity;

interface VersionedEntity
{
    public function getValidFrom(): ?\DateTimeImmutable;
}
