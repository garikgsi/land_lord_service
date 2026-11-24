<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'idx_object_meters_provider_id', columns: ['object_unity_provider_id'])]
#[ORM\Table(name: 'object_meters')]
#[ORM\Index(name: 'idx_object_meters_object_id', columns: ['object_id'])]
#[ORM\UniqueConstraint(name: 'uq_object_meters_meter', columns: ['meter_id'])]
class ObjectMeter
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'object_id', nullable: false, onDelete: 'RESTRICT')]
        private RentalObject $object,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'meter_id', nullable: false, onDelete: 'RESTRICT')]
        private Meter $meter,
        #[ORM\Column(name: 'comment', type: Types::TEXT, nullable: true)]
        private ?string $comment = null,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'object_unity_provider_id', nullable: true, onDelete: 'RESTRICT')]
        private ?ObjectUnityProvider $objectUnityProvider = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getObject(): RentalObject
    {
        return $this->object;
    }

    public function setObject(RentalObject $object): void
    {
        $this->object = $object;
    }

    public function getMeter(): Meter
    {
        return $this->meter;
    }

    public function setMeter(Meter $meter): void
    {
        $this->meter = $meter;
    }

    public function getObjectUnityProvider(): ?ObjectUnityProvider
    {
        return $this->objectUnityProvider;
    }

    public function setObjectUnityProvider(?ObjectUnityProvider $provider): void
    {
        $this->objectUnityProvider = $provider;
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
