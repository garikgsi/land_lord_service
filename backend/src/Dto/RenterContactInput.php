<?php

declare(strict_types=1);

namespace App\Dto;

use App\Validator\PhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RenterContactInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите имя.', normalizer: 'trim')]
        public string $name = '',
        public ?string $lastName = null,
        public ?string $patronymic = null,
        #[PhoneNumber]
        public ?string $phone = null,
        #[Assert\Email(message: 'Введите корректный email.', normalizer: 'trim')]
        public ?string $email = null,
    ) {
    }
}
