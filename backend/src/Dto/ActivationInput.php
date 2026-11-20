<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use App\Validator\PhoneNumber;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ActivationInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex('/\A[a-f0-9]{64}\z/', htmlPattern: '^[a-f0-9]{64}$')]
        #[OA\Property(writeOnly: true)]
        #[\SensitiveParameter]
        public string $token,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: User::NAME_MAX_LENGTH)]
        public string $firstName,
        #[Assert\NotBlank]
        #[Assert\Length(min: PasswordInput::MIN_LENGTH, max: PasswordInput::MAX_LENGTH)]
        #[OA\Property(writeOnly: true)]
        #[\SensitiveParameter]
        public string $password,
        #[Assert\Length(max: User::NAME_MAX_LENGTH)]
        public ?string $lastName = null,
        #[PhoneNumber]
        #[Assert\Length(max: User::PHONE_MAX_LENGTH)]
        public ?string $phone = null,
        #[Assert\Length(max: User::POSITION_MAX_LENGTH)]
        public ?string $position = null,
    ) {
    }
}
