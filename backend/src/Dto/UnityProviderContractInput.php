<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class UnityProviderContractInput
{
    public function __construct(
        #[Assert\Positive]
        public int $objectId = 0,
        #[Assert\Positive]
        public int $unityProviderId = 0,
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $expiryDate = '',
        #[Assert\Length(max: 255)]
        public ?string $contractNum = null,
        #[Assert\Date]
        public ?string $contractDate = null,
        public bool $isSuspended = false,
        #[Assert\Count(min: 1, max: 1, minMessage: 'Выберите коммуникацию.', maxMessage: 'У договора должна быть одна коммуникация.')]
        #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
        public array $unityIds = [],
        #[Assert\Positive]
        public ?int $meterId = null,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (null !== $this->contractDate && $this->contractDate > $this->expiryDate) {
            $context->buildViolation('Дата договора не может быть позже окончания.')->atPath('contractDate')->addViolation();
        }
    }
}
