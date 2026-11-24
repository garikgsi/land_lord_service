<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Meter;
use App\Entity\Space;
use App\Entity\SpaceMeter;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SpaceMeterService
{
    public function __construct(private EntityManagerInterface $em, private LandLordService $landLords)
    {
    }

    public function find(Meter $meter): ?SpaceMeter
    {
        return $this->em->getRepository(SpaceMeter::class)->findOneBy(['meter' => $meter]);
    }

    public function present(SpaceMeter $record): array
    {
        $object = $record->getSpace()->getObject();

        return [
            'type' => 'space', 'id' => $record->getSpace()->getId(), 'name' => $record->getSpace()->getName(),
            'object' => ['id' => $object->getId(), 'name' => $object->getName(), 'readingDay' => $object->getReadingDay(), 'landLord' => $this->landLords->present($object->getLandLord())],
            'comment' => $record->getComment(), 'provider' => null,
        ];
    }

    public function save(Meter $meter, Space $owner, ?string $comment): void
    {
        $record = $this->find($meter) ?? new SpaceMeter($owner, $meter);
        $record->setSpace($owner);
        $record->setComment($comment);
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
