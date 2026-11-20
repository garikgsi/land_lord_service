<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'meter_readings')]
#[ORM\UniqueConstraint(name: 'uq_meter_readings_moment', columns: ['meter_id', 'meter_reading_date'])]
class MeterReading
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'meter_id', nullable: false, onDelete: 'RESTRICT')]
        private Meter $meter,
        #[ORM\Column(name: 'meter_reading_date', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $meterReadingDate,
        #[ORM\Column(name: 'meter_read', type: Types::INTEGER)]
        private int $meterRead,
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

    public function getMeterReadingDate(): \DateTimeImmutable
    {
        return $this->meterReadingDate;
    }

    public function setMeterReadingDate(\DateTimeImmutable $meterReadingDate): void
    {
        $this->meterReadingDate = $meterReadingDate;
    }

    public function getMeterRead(): int
    {
        return $this->meterRead;
    }

    public function setMeterRead(int $meterRead): void
    {
        $this->meterRead = $meterRead;
    }
}
