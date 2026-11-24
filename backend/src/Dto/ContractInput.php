<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class ContractInput
{
    public function __construct(
        #[Assert\Positive(message: 'Выберите объект.')]
        public int $objectId = 0,
        #[Assert\Positive(message: 'Выберите арендатора.')]
        public int $renterId = 0,
        #[Assert\Positive(message: 'Выберите тип договора.')]
        public int $contractTypeId = 1,
        #[Assert\NotBlank(message: 'Укажите номер договора.', normalizer: 'trim')]
        public string $contractNum = '',
        #[Assert\NotBlank(message: 'Укажите начало аренды.')]
        #[Assert\Date(message: 'Укажите корректную дату.')]
        public string $contractDate = '',
        #[Assert\NotBlank(message: 'Укажите окончание аренды.')]
        #[Assert\Date(message: 'Укажите корректную дату.')]
        public string $expiryDate = '',
        #[Assert\NotBlank(message: 'Укажите ежемесячный платёж.')]
        #[Assert\Regex(pattern: '/^[0-9]+(?:\.[0-9]+)?$/D', message: 'Введите неотрицательную сумму.')]
        public string $monthlyPayment = '',
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[0-9]+(?:\.[0-9]+)?$/D', message: 'Введите неотрицательную сумму.')]
        public string $securityPayment = '0',
        #[Assert\NotBlank(message: 'Введите положительную площадь.', allowNull: true)]
        #[Assert\Regex(pattern: '/^(?=.*[1-9])[0-9]+(?:\.[0-9]+)?$/D', message: 'Введите положительную площадь.')]
        public ?string $square = null,
        public string $spaceName = '',
        #[Assert\Positive]
        public ?int $previousContractId = null,
        #[Assert\Date(message: 'Укажите корректную дату.')]
        public ?string $increaseDate = null,
        #[Assert\Regex(pattern: '/^[0-9]+(?:\.[0-9]+)?$/D', message: 'Введите неотрицательный процент.')]
        public ?string $increasePercent = null,
        public bool $isSigned = true,
        #[Assert\Regex(pattern: '/^[a-f0-9]{64}$/D', message: 'Обновите расчёт и подтвердите начисление.')]
        public ?string $signingConfirmation = null,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ('' !== $this->contractDate && '' !== $this->expiryDate && $this->expiryDate < $this->contractDate) {
            $context->buildViolation('Окончание не может быть раньше начала аренды.')->atPath('expiryDate')->addViolation();
        }
        if ((null === $this->increaseDate) !== (null === $this->increasePercent) || '' === $this->increaseDate || '' === $this->increasePercent) {
            $context->buildViolation('Заполните дату и процент индексации вместе либо очистите оба поля.')->atPath('increaseDate')->addViolation();
        }
    }
}
