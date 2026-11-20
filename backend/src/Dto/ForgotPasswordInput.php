<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ForgotPasswordInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[OA\Property(format: 'email')]
        #[Assert\Email]
        #[Assert\Length(max: User::EMAIL_MAX_LENGTH)]
        public string $email,
    ) {
    }
}
