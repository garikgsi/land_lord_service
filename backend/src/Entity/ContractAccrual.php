<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'contract_accruals')]
#[ORM\UniqueConstraint(name: 'uq_contract_accruals_month', columns: ['contract_id', 'period_month'])]
class ContractAccrual
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'contract_id', nullable: false, onDelete: 'RESTRICT')]
        private Contract $contract,
        #[ORM\Column(name: 'period_month', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $periodMonth,
        #[ORM\Column(name: 'period_start', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $periodStart,
        #[ORM\Column(name: 'period_end', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $periodEnd,
        #[ORM\Column(name: 'monthly_payment', type: Types::DECIMAL, columnDefinition: 'NUMERIC')]
        private string $monthlyPayment,
        #[ORM\Column(name: 'amount', type: Types::DECIMAL, columnDefinition: 'NUMERIC')]
        private string $amount,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContract(): Contract
    {
        return $this->contract;
    }

    public function setContract(Contract $contract): void
    {
        $this->contract = $contract;
    }

    public function getPeriodMonth(): \DateTimeImmutable
    {
        return $this->periodMonth;
    }

    public function setPeriodMonth(\DateTimeImmutable $periodMonth): void
    {
        $this->periodMonth = $periodMonth;
    }

    public function getPeriodStart(): \DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(\DateTimeImmutable $periodStart): void
    {
        $this->periodStart = $periodStart;
    }

    public function getPeriodEnd(): \DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(\DateTimeImmutable $periodEnd): void
    {
        $this->periodEnd = $periodEnd;
    }

    public function getMonthlyPayment(): string
    {
        return $this->monthlyPayment;
    }

    public function setMonthlyPayment(string $monthlyPayment): void
    {
        $this->monthlyPayment = $monthlyPayment;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): void
    {
        $this->amount = $amount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
