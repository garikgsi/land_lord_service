<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MeterListQuery
{
    public function __construct(
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(100000)]
        public int $page = 1,
        #[Assert\Length(max: 120)]
        public string $query = '',
        #[Assert\Positive]
        public ?int $objectId = null,
        #[Assert\Positive]
        public ?int $spaceId = null,
        #[Assert\Choice(choices: ['all', 'installed', 'uninstalled'])]
        public string $state = 'all',
        #[Assert\Positive]
        public ?int $utilityId = null,
        public bool $objectOnly = false,
    ) {
    }
}
