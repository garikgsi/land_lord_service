<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegistryListQuery
{
    public function __construct(
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(100000)]
        public int $page = 1,
        #[Assert\Length(max: 120)]
        public string $query = '',
    ) {
    }
}
