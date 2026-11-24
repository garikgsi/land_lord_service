<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MeterReadingInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите дату и время снятия показаний.')]
        #[Assert\DateTime(format: 'Y-m-d\TH:i:s', message: 'Укажите дату и время в формате YYYY-MM-DDTHH:MM:SS.')]
        public string $meterReadingDate = '',
        #[Assert\NotNull(message: 'Укажите показание.')]
        #[Assert\PositiveOrZero]
        #[Assert\LessThanOrEqual(2147483647, message: 'Укажите целое число')]
        #[Assert\DivisibleBy(value: 1, message: 'Укажите целое число')]
        public int|float|null $meterRead = null,
    ) {
    }
}
