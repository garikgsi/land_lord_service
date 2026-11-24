<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MeterInput
{
    public function __construct(
        #[Assert\Positive(message: 'Выберите коммуникацию.')]
        public int $utilityId = 0,
        #[Assert\NotBlank(message: 'Укажите дату производства.')]
        #[Assert\Date(message: 'Укажите корректную дату.')]
        public string $createDate = '',
        #[Assert\NotNull(message: 'Укажите начальные показания.')]
        #[Assert\PositiveOrZero]
        #[Assert\LessThanOrEqual(2147483647)]
        #[Assert\DivisibleBy(value: 1, message: 'Укажите целое число.')]
        public int|float|null $startRead = null,
        #[Assert\Positive(message: 'Укажите положительный межповерочный интервал.')]
        #[Assert\LessThanOrEqual(2147483647)]
        #[Assert\DivisibleBy(value: 1, message: 'Укажите целое число.')]
        public int|float $verificationIntervalDays = 0,
        public ?string $serialNumber = null,
        #[Assert\Date]
        public ?string $verificationDate = null,
        #[Assert\Range(min: 1, max: 31)]
        #[Assert\DivisibleBy(value: 1, message: 'Укажите целое число.')]
        public int|float|null $readingDay = null,
        #[Assert\Positive]
        public ?int $objectId = null,
        #[Assert\Positive]
        public ?int $spaceId = null,
        public ?string $comment = null,
        #[Assert\Positive]
        public ?int $objectUnityProviderId = null,
    ) {
    }
}
