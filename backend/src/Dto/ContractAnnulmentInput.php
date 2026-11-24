<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ContractAnnulmentInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите дату аннулирования.')]
        #[Assert\Date(message: 'Укажите корректную дату.')]
        public string $annulmentDate = '',
    ) {
    }
}
