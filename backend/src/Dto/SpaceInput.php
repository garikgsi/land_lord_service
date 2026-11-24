<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SpaceInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите название помещения.', normalizer: 'trim')]
        public string $name = '',
    ) {
    }
}
