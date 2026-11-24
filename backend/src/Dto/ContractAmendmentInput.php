<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ContractAmendmentInput
{
    public function __construct(
        #[Assert\Positive(message: 'Выберите арендатора.')]
        public int $renterId = 0,
        #[Assert\NotBlank(message: 'Укажите окончание аренды.')]
        #[Assert\Date(message: 'Укажите корректную дату.')]
        public string $expiryDate = '',
        #[Assert\NotBlank(message: 'Укажите ежемесячный платёж.')]
        #[Assert\Regex(pattern: '/^[0-9]+(?:\.[0-9]+)?$/D', message: 'Введите неотрицательную сумму.')]
        public string $monthlyPayment = '',
    ) {
    }
}
