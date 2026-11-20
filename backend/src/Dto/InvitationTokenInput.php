<?php

declare(strict_types=1);

namespace App\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class InvitationTokenInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex('/\A[a-f0-9]{64}\z/', htmlPattern: '^[a-f0-9]{64}$')]
        #[OA\Property(writeOnly: true)]
        #[\SensitiveParameter]
        public string $token,
    ) {
    }
}
