<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MeterVerificationInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите дату поверки.')]
        #[Assert\Date]
        public string $verificationDate = '',
    ) {
    }
}
