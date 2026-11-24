<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SpaceAvailabilityQuery
{
    public function __construct(
        #[Assert\Positive]
        public ?int $contractId = null,
    ) {
    }
}
