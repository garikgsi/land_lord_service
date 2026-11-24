<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Colorable;
use App\Entity\EntityColor;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EntityColorService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function color(Colorable $entity): ?string
    {
        return $this->em->getRepository(EntityColor::class)->findOneBy([
            'entityType' => $entity::colorType(), 'entityId' => $entity->getId(),
        ])?->getColor();
    }

    /** Caller saves the owner and colour in the same transaction and locks existing owners. */
    public function save(Colorable $entity, ?string $value): EntityColor
    {
        $id = $entity->getId() ?? throw new \LogicException('Persist the owner before setting its colour.');
        $color = $this->em->getRepository(EntityColor::class)->findOneBy([
            'entityType' => $entity::colorType(), 'entityId' => $id,
        ]) ?? new EntityColor($entity::colorType(), $id);
        $color->setColor($value);
        $this->em->persist($color);
        $this->em->flush();

        return $color;
    }
}
