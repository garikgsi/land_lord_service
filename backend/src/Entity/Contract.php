<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'contracts')]
// Backing index of the PostgreSQL EXCLUDE constraint; its SQL is managed by migrations.
#[ORM\Index(name: 'ex_contracts_space_period', columns: ['space_id'], options: ['where' => '(NOT is_deleted)'])]
#[ORM\Index(name: 'idx_contracts_space_id', columns: ['space_id'])]
#[ORM\Index(name: 'idx_contracts_renter_id', columns: ['renter_id'])]
#[ORM\Index(name: 'idx_contracts_land_lord_id', columns: ['land_lord_id'])]
#[ORM\Index(name: 'idx_contracts_contract_type_id', columns: ['contract_type_id'])]
#[ORM\Index(name: 'idx_contracts_expiry_date', columns: ['expiry_date'])]
#[ORM\Index(name: 'idx_contracts_increase_date', columns: ['increase_date'])]
class Contract implements VersionedEntity
{
    use HasDeletion;

    use HasValidity;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'space_id', nullable: false, onDelete: 'RESTRICT')]
        private Space $space,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'renter_id', nullable: false, onDelete: 'RESTRICT')]
        private Renter $renter,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'land_lord_id', nullable: false, onDelete: 'RESTRICT')]
        private LandLord $landLord,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'contract_type_id', nullable: false, onDelete: 'RESTRICT')]
        private ContractType $contractType,
        #[ORM\Column(name: 'security_payment', type: Types::DECIMAL, columnDefinition: 'NUMERIC')]
        private string $securityPayment,
        #[ORM\Column(name: 'expiry_date', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $expiryDate,
        #[ORM\Column(name: 'contract_date', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $contractDate,
        #[ORM\Column(name: 'contract_num', type: Types::TEXT)]
        private string $contractNum,
        #[ORM\Column(name: 'monthly_payment', type: Types::DECIMAL, columnDefinition: 'NUMERIC')]
        private string $monthlyPayment,
        #[ORM\Column(name: 'annulment_date', type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $annulmentDate = null,
        #[ORM\Column(name: 'increase_date', type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $increaseDate = null,
        #[ORM\Column(name: 'increase_percent', type: Types::DECIMAL, nullable: true, columnDefinition: 'NUMERIC')]
        private ?string $increasePercent = null,
    ) {
    }

    #[ORM\Column(name: 'increase_interval_months', nullable: true)]
    private ?int $increaseIntervalMonths = null;

    #[ORM\Column(name: 'last_indexation_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastIndexationDate = null;

    #[ORM\Column(name: 'is_signed', options: ['default' => true])]
    private bool $isSigned = true;

    public function isSigned(): bool
    {
        return $this->isSigned;
    }

    public function setSigned(bool $isSigned): void
    {
        $this->isSigned = $isSigned;
    }

    public function getLastIndexationDate(): ?\DateTimeImmutable
    {
        return $this->lastIndexationDate;
    }

    public function setLastIndexationDate(?\DateTimeImmutable $date): void
    {
        $this->lastIndexationDate = $date;
    }

    public function getIncreaseIntervalMonths(): ?int
    {
        return $this->increaseIntervalMonths;
    }

    public function setIncreaseIntervalMonths(?int $months): void
    {
        $this->increaseIntervalMonths = $months;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSpace(): Space
    {
        return $this->space;
    }

    public function setSpace(Space $space): void
    {
        $this->space = $space;
    }

    public function getRenter(): Renter
    {
        return $this->renter;
    }

    public function setRenter(Renter $renter): void
    {
        $this->renter = $renter;
    }

    public function getLandLord(): LandLord
    {
        return $this->landLord;
    }

    public function setLandLord(LandLord $landLord): void
    {
        $this->landLord = $landLord;
    }

    public function getContractType(): ContractType
    {
        return $this->contractType;
    }

    public function setContractType(ContractType $contractType): void
    {
        $this->contractType = $contractType;
    }

    public function getSecurityPayment(): string
    {
        return $this->securityPayment;
    }

    public function setSecurityPayment(string $securityPayment): void
    {
        $this->securityPayment = $securityPayment;
    }

    public function getExpiryDate(): \DateTimeImmutable
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(\DateTimeImmutable $expiryDate): void
    {
        $this->expiryDate = $expiryDate;
    }

    public function getContractDate(): \DateTimeImmutable
    {
        return $this->contractDate;
    }

    public function setContractDate(\DateTimeImmutable $contractDate): void
    {
        $this->contractDate = $contractDate;
    }

    public function getContractNum(): string
    {
        return $this->contractNum;
    }

    public function setContractNum(string $contractNum): void
    {
        $this->contractNum = $contractNum;
    }

    public function getMonthlyPayment(): string
    {
        return $this->monthlyPayment;
    }

    public function setMonthlyPayment(string $monthlyPayment): void
    {
        $this->monthlyPayment = $monthlyPayment;
    }

    public function getAnnulmentDate(): ?\DateTimeImmutable
    {
        return $this->annulmentDate;
    }

    public function setAnnulmentDate(?\DateTimeImmutable $annulmentDate): void
    {
        $this->annulmentDate = $annulmentDate;
    }

    public function getIncreaseDate(): ?\DateTimeImmutable
    {
        return $this->increaseDate;
    }

    public function setIncreaseDate(?\DateTimeImmutable $increaseDate): void
    {
        $this->increaseDate = $increaseDate;
    }

    public function getIncreasePercent(): ?string
    {
        return $this->increasePercent;
    }

    public function setIncreasePercent(?string $increasePercent): void
    {
        $this->increasePercent = $increasePercent;
    }
}
