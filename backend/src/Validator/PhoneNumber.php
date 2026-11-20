<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class PhoneNumber extends Regex
{
    public function __construct(?array $groups = null, mixed $payload = null)
    {
        parent::__construct(
            pattern: '/\A\+7(?:[0-9]{10}| (?:\([0-9]{3}\)|[0-9]{3}) [0-9]{3}-[0-9]{2}-[0-9]{2})\z/',
            htmlPattern: '^\\+7(?:[0-9]{10}| (?:\\([0-9]{3}\\)|[0-9]{3}) [0-9]{3}-[0-9]{2}-[0-9]{2})$',
            message: 'Введите телефон полностью: +7 (ХХХ) ХХХ-ХХ-ХХ.',
            groups: $groups,
            payload: $payload,
        );
    }

    public function validatedBy(): string
    {
        return RegexValidator::class;
    }
}
