<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\NamedRecordInput;
use App\Dto\RegistryListQuery;
use App\Entity\Ownership;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OwnershipService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(int $id): ?Ownership
    {
        return $this->em->getRepository(Ownership::class)->find($id);
    }

    public function present(Ownership $record): array
    {
        return ['id' => $record->getId(), 'name' => $record->getName()];
    }

    public function list(RegistryListQuery $input): array
    {
        $query = $this->em->getRepository(Ownership::class)->createQueryBuilder('r');
        if ('' !== trim($input->query)) {
            $query->andWhere('LOWER(r.name) LIKE :query')->setParameter('query', '%'.mb_strtolower(trim($input->query)).'%');
        }
        $items = array_map($this->present(...), $query->orderBy('r.id', 'ASC')->getQuery()->getResult());

        return ['items' => $items, 'total' => count($items), 'page' => 1, 'pageSize' => count($items)];
    }

    public function save(NamedRecordInput $input, ?Ownership $record = null): Ownership
    {
        $record ??= new Ownership(trim($input->name));
        $record->setName(trim($input->name));
        $this->em->persist($record);
        $this->em->flush();

        return $record;
    }

    public function remove(Ownership $record): void
    {
        $this->em->wrapInTransaction(function () use ($record): void {
            $this->em->refresh($record, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            SoftDeletionGuard::assertUnused($this->em, $record);
            $record->markDeleted();
            $this->em->flush();
        });
    }
}
