<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PasswordInput
{
    public const MIN_LENGTH = 10;
    public const MAX_LENGTH = 4096;

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: self::MIN_LENGTH, max: self::MAX_LENGTH)]
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}
