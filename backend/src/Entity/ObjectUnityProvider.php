<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'object_unity_providers')]
#[ORM\Index(name: 'idx_object_unity_providers_unity_provider_id', columns: ['unity_provider_id'])]
#[ORM\Index(name: 'idx_object_unity_providers_object_id', columns: ['object_id'])]
class ObjectUnityProvider
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'unity_provider_id', nullable: false, onDelete: 'RESTRICT')]
        private UnityProvider $unityProvider,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'object_id', nullable: false, onDelete: 'RESTRICT')]
        private RentalObject $object,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUnityProvider(): UnityProvider
    {
        return $this->unityProvider;
    }

    public function setUnityProvider(UnityProvider $unityProvider): void
    {
        $this->unityProvider = $unityProvider;
    }

    public function getObject(): RentalObject
    {
        return $this->object;
    }

    public function setObject(RentalObject $object): void
    {
        $this->object = $object;
    }
}
