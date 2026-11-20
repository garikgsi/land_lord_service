<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'contract_accruals_correction')]
#[ORM\Index(name: 'idx_contract_accruals_correction_latest', columns: ['contract_accrual_id', 'created_at', 'id'])]
#[ORM\Index(name: 'idx_contract_accruals_correction_author', columns: ['created_by_id'])]
class ContractAccrualCorrection
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'contract_accrual_id', nullable: false, onDelete: 'RESTRICT')]
        private ContractAccrual $contractAccrual,
        #[ORM\Column(name: 'period_start', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $periodStart,
        #[ORM\Column(name: 'period_end', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $periodEnd,
        #[ORM\Column(name: 'monthly_payment', type: Types::DECIMAL, columnDefinition: 'NUMERIC')]
        private string $monthlyPayment,
        #[ORM\Column(name: 'amount', type: Types::DECIMAL, columnDefinition: 'NUMERIC')]
        private string $amount,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'created_by_id', nullable: false, onDelete: 'RESTRICT')]
        private User $createdBy,
        #[ORM\Column(name: 'comment', type: Types::TEXT, nullable: true)]
        private ?string $comment = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContractAccrual(): ContractAccrual
    {
        return $this->contractAccrual;
    }

    public function setContractAccrual(ContractAccrual $contractAccrual): void
    {
        $this->contractAccrual = $contractAccrual;
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

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }
}
