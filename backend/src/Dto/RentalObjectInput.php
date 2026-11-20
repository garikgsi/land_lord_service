<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RentalObjectInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите название.', normalizer: 'trim')]
        public string $name = '',
        #[Assert\NotBlank(message: 'Укажите адрес.', normalizer: 'trim')]
        public string $address = '',
        #[Assert\Regex(pattern: '/^(?=.*[1-9])[0-9]+(?:\.[0-9]+)?$/D', message: 'Введите положительную площадь без округления.')]
        public string $square = '',
        #[Assert\Positive(message: 'Выберите арендодателя.')]
        public int $landLordId = 0,
        #[Assert\Range(notInRangeMessage: 'Укажите день от 1 до 31.', min: 1, max: 31)]
        public int $readingDay = 25,
    ) {
    }
}
