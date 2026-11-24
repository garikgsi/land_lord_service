<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class InputException extends UnprocessableEntityHttpException
{
    public readonly array $errors;

    public function __construct(string $field, string $message)
    {
        $this->errors = [$field => $message];
        parent::__construct($message);
    }
}
