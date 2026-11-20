<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LandLordInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите наименование.', normalizer: 'trim')]
        public string $name = '',
        #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/D', message: 'Укажите цвет в формате #RRGGBB.')]
        #[Assert\Length(exactly: 7, exactMessage: 'Укажите цвет в формате #RRGGBB.')]
        public ?string $color = null,
    ) {
    }
}
