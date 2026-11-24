<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class MeterInstallationInput
{
    public function __construct(
        #[Assert\Positive]
        public ?int $objectId = null,
        #[Assert\Positive]
        public ?int $spaceId = null,
        public ?string $comment = null,
        #[Assert\Positive]
        public ?int $objectUnityProviderId = null,
        public bool $requireUninstalled = false,
        #[Assert\Positive]
        public ?int $utilityId = null,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (null !== $this->objectId && null !== $this->spaceId) {
            $context->buildViolation('Выберите только одно место: объект или помещение.')->atPath('spaceId')->addViolation();
        }
    }
}
