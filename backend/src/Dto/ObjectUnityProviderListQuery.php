<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ObjectUnityProviderListQuery
{
    public function __construct(
        #[Assert\Length(max: 120)]
        public string $query = '',
        #[Assert\Positive]
        public ?int $unityId = null,
    ) {
    }
}
