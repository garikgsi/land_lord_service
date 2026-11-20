<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'contract_payments')]
#[ORM\Index(name: 'idx_contract_payments_contract_id_payment_date', columns: ['contract_id', 'payment_date'])]
#[ORM\Index(name: 'idx_contract_payments_created_by_id', columns: ['created_by_id'])]
class ContractPayment
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
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'created_by_id', nullable: false, onDelete: 'RESTRICT')]
        private User $createdBy,
        #[ORM\Column(name: 'payment_date', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $paymentDate,
        #[ORM\Column(name: 'amount', type: Types::DECIMAL, columnDefinition: 'NUMERIC')]
        private string $amount,
        #[ORM\Column(name: 'comment', type: Types::TEXT, nullable: true)]
        private ?string $comment = null,
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

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getPaymentDate(): \DateTimeImmutable
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(\DateTimeImmutable $paymentDate): void
    {
        $this->paymentDate = $paymentDate;
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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }
}
