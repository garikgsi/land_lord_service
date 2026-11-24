<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UtilityListQuery
{
    public function __construct(
        #[Assert\Length(max: 120)]
        public string $query = '',
        public bool $meterOnly = false,
        #[Assert\Positive]
        public ?int $unityId = null,
    ) {
    }
}
