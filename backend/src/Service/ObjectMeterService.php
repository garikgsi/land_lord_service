<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Meter;
use App\Entity\ObjectMeter;
use App\Entity\ObjectUnityProvider;
use App\Entity\RentalObject;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ObjectMeterService
{
    public function __construct(private EntityManagerInterface $em, private ObjectUnityProviderService $providers, private LandLordService $landLords)
    {
    }

    public function find(Meter $meter): ?ObjectMeter
    {
        return $this->em->getRepository(ObjectMeter::class)->findOneBy(['meter' => $meter]);
    }

    public function present(ObjectMeter $record): array
    {
        $object = $record->getObject();

        return [
            'type' => 'object', 'id' => $record->getObject()->getId(), 'name' => $record->getObject()->getName(),
            'object' => ['id' => $object->getId(), 'name' => $object->getName(), 'readingDay' => $object->getReadingDay(), 'landLord' => $this->landLords->present($object->getLandLord())],
            'comment' => $record->getComment(),
            'provider' => null === $record->getObjectUnityProvider() ? null : $this->providers->present($record->getObjectUnityProvider()),
        ];
    }

    public function save(Meter $meter, RentalObject $owner, ?string $comment, ?ObjectUnityProvider $provider = null): void
    {
        $record = $this->find($meter) ?? new ObjectMeter($owner, $meter);
        $record->setObject($owner);
        $record->setComment($comment);
        $record->setObjectUnityProvider($provider);
        $this->em->persist($record);
        $this->em->flush();
    }

    public function clear(Meter $meter): void
    {
        $record = $this->find($meter);
        if (null !== $record) {
            $this->em->remove($record);
            $this->em->flush();
        }
    }
}
