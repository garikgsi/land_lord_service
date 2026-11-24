<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class ContractCalendarQuery
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $start = '',
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $end = '',
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->start);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->end);
        if (false === $start || false === $end) {
            return;
        }
        if ($end <= $start || $start->diff($end)->days > 370) {
            $context->buildViolation('Выберите период не более года; конец периода должен быть позже начала.')->atPath('end')->addViolation();
        }
    }
}
