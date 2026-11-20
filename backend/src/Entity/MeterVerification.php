<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'meter_verifications')]
#[ORM\Index(name: 'idx_meter_verifications_meter_id_verification_date', columns: ['meter_id', 'verification_date'])]
class MeterVerification
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'meter_id', nullable: false, onDelete: 'RESTRICT')]
        private Meter $meter,
        #[ORM\Column(name: 'verification_date', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $verificationDate,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMeter(): Meter
    {
        return $this->meter;
    }

    public function setMeter(Meter $meter): void
    {
        $this->meter = $meter;
    }

    public function getVerificationDate(): \DateTimeImmutable
    {
        return $this->verificationDate;
    }

    public function setVerificationDate(\DateTimeImmutable $verificationDate): void
    {
        $this->verificationDate = $verificationDate;
    }
}
