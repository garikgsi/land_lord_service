<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ObjectUnityProviderInput
{
    public function __construct(
        #[Assert\Positive(message: 'Выберите поставщика.')]
        public int $unityProviderId = 0,
        #[Assert\Positive(message: 'Выберите коммуникацию.')]
        public int $unityId = 0,
    ) {
    }
}
