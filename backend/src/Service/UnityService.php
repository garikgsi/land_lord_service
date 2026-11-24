<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\UnityInput;
use App\Dto\UtilityListQuery;
use App\Entity\Unity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UnityService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(int $id): ?Unity
    {
        return $this->em->getRepository(Unity::class)->find($id);
    }

    public function present(Unity $record): array
    {
        return ['id' => $record->getId(), 'name' => $record->getName(), 'useMeter' => $record->getUseMeter(), 'unit' => $record->getUnit()];
    }

    public function list(UtilityListQuery $input): array
    {
        $query = $this->em->getRepository(Unity::class)->createQueryBuilder('u');
        if ('' !== trim($input->query)) {
            $query->andWhere('LOWER(u.name) LIKE :query')->setParameter('query', '%'.mb_strtolower(trim($input->query)).'%');
        }
        if ($input->meterOnly) {
            $query->andWhere('u.useMeter = true');
        }
        $items = array_map($this->present(...), $query->orderBy('u.name', 'ASC')->addOrderBy('u.id', 'ASC')->getQuery()->getResult());

        return ['items' => $items, 'total' => count($items), 'page' => 1, 'pageSize' => count($items)];
    }

    public function save(UnityInput $input, ?Unity $record = null): Unity
    {
        $record ??= new Unity(trim($input->name), $input->useMeter);
        $record->setName(trim($input->name));
        $record->setUseMeter($input->useMeter);
        $record->setUnit(null === $input->unit || '' === trim($input->unit) ? null : trim($input->unit));
        $this->em->persist($record);
        $this->em->flush();

        return $record;
    }

    public function remove(Unity $record): void
    {
        $this->em->wrapInTransaction(function () use ($record): void {
            $this->em->refresh($record, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            SoftDeletionGuard::assertUnused($this->em, $record);
            $record->markDeleted();
            $this->em->flush();
        });
    }
}
