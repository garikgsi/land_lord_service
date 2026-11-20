<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RentalObjectMeterSettingsInput
{
    public function __construct(
        #[Assert\Range(notInRangeMessage: 'Укажите день от 1 до 31.', min: 1, max: 31)]
        public int $readingDay = 0,
    ) {
    }
}
