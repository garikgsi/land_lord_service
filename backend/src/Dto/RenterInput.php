<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RenterInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите наименование.', normalizer: 'trim')]
        public string $name = '',
        #[Assert\Positive(message: 'Выберите форму собственности.')]
        public int $ownershipId = 0,
        #[Assert\Regex(pattern: '/^(?:[0-9]{10}|[0-9]{12})$/D', message: 'ИНН должен содержать 10 или 12 цифр.')]
        public ?string $inn = null,
    ) {
    }
}
