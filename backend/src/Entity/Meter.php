<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'meters')]
#[ORM\Index(name: 'idx_meters_utility_id', columns: ['utility_id'])]
class Meter implements VersionedEntity
{
    use HasDeletion;

    use HasValidity;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'utility_id', nullable: false, onDelete: 'RESTRICT')]
        private Unity $utility,
        #[ORM\Column(name: 'create_date', type: Types::DATE_IMMUTABLE, options: ['comment' => 'User-entered production date, also the date of start_read; never the database insertion date'])]
        private \DateTimeImmutable $createDate,
        #[ORM\Column(name: 'start_read', type: Types::INTEGER)]
        private int $startRead,
        #[ORM\Column(name: 'verification_interval_days', type: Types::INTEGER)]
        private int $verificationIntervalDays,
        #[ORM\Column(name: 'serial_number', type: Types::TEXT, nullable: true)]
        private ?string $serialNumber = null,
        #[ORM\Column(name: 'verification_date', type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $verificationDate = null,
        #[ORM\Column(name: 'reading_day', type: Types::SMALLINT, nullable: true)]
        private ?int $readingDay = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtility(): Unity
    {
        return $this->utility;
    }

    public function setUtility(Unity $utility): void
    {
        $this->utility = $utility;
    }

    public function getCreateDate(): \DateTimeImmutable
    {
        return $this->createDate;
    }

    public function setCreateDate(\DateTimeImmutable $createDate): void
    {
        $this->createDate = $createDate;
    }

    public function getStartRead(): int
    {
        return $this->startRead;
    }

    public function setStartRead(int $startRead): void
    {
        $this->startRead = $startRead;
    }

    public function getVerificationIntervalDays(): int
    {
        return $this->verificationIntervalDays;
    }

    public function setVerificationIntervalDays(int $verificationIntervalDays): void
    {
        $this->verificationIntervalDays = $verificationIntervalDays;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(?string $serialNumber): void
    {
        $this->serialNumber = $serialNumber;
    }

    public function getVerificationDate(): ?\DateTimeImmutable
    {
        return $this->verificationDate;
    }

    public function setVerificationDate(?\DateTimeImmutable $verificationDate): void
    {
        $this->verificationDate = $verificationDate;
    }

    public function getReadingDay(): ?int
    {
        return $this->readingDay;
    }

    public function setReadingDay(?int $readingDay): void
    {
        $this->readingDay = $readingDay;
    }
}
