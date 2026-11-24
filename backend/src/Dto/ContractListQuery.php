<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class ContractListQuery
{
    public function __construct(
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(100000)]
        public int $page = 1,
        #[Assert\Length(max: 120)]
        public string $query = '',
        #[Assert\Positive]
        public ?int $objectId = null,
        #[Assert\Positive]
        public ?int $renterId = null,
        #[Assert\Positive]
        public ?int $spaceId = null,
        #[Assert\Date]
        public ?string $expiryFrom = null,
        #[Assert\Date]
        public ?string $expiryTo = null,
        #[Assert\Choice(choices: ['all', 'active', 'upcoming', 'finished', 'cancelled'])]
        public string $status = 'all',
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (null !== $this->expiryFrom && null !== $this->expiryTo && $this->expiryFrom > $this->expiryTo) {
            $context->buildViolation('Начало периода не может быть позже окончания.')->atPath('expiryFrom')->addViolation();
        }
    }
}
