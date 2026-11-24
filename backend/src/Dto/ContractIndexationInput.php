<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class ContractIndexationInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите дату индексации.')]
        #[Assert\Date(message: 'Укажите корректную дату.')]
        public string $increaseDate = '',
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^-?[0-9]+(?:\.[0-9]+)?$/D', message: 'Укажите корректный процент.')]
        public string $increasePercent = '',
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[0-9]+(?:\.[0-9]+)?$/D', message: 'Укажите текущую сумму.')]
        public string $currentMonthlyPayment = '',
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[0-9]+(?:\.[0-9]+)?$/D', message: 'Введите неотрицательную сумму.')]
        public string $newMonthlyPayment = '',
        #[Assert\Choice(choices: ['percent', 'amount'])]
        public string $calculationMode = 'percent',
        public bool $periodic = false,
        #[Assert\Positive(message: 'Укажите положительное количество месяцев.')]
        #[Assert\LessThanOrEqual(value: 2147483647, message: 'Превышен допустимый интервал.')]
        public ?int $intervalMonths = null,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->periodic && null === $this->intervalMonths) {
            $context->buildViolation('Укажите периодичность в месяцах.')->atPath('intervalMonths')->addViolation();
        }
        if (!$this->periodic && null !== $this->intervalMonths) {
            $context->buildViolation('Без периодической индексации интервал должен быть пустым.')->atPath('intervalMonths')->addViolation();
        }
    }
}
