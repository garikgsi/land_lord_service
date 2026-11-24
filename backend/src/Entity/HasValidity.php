<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait HasValidity
{
    #[ORM\Column(name: 'valid_from', type: Types::DATETIME_IMMUTABLE, nullable: true, insertable: false, updatable: false, columnDefinition: 'TIMESTAMP(6) WITHOUT TIME ZONE', generated: 'ALWAYS')]
    private ?\DateTimeImmutable $validFrom = null;

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }
}
