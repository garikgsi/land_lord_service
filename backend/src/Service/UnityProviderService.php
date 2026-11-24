<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\RegistryListQuery;
use App\Dto\UnityProviderInput;
use App\Entity\UnityProvider;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UnityProviderService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(int $id): ?UnityProvider
    {
        return $this->em->getRepository(UnityProvider::class)->find($id);
    }

    public function present(UnityProvider $record): array
    {
        return ['id' => $record->getId(), 'name' => $record->getName()];
    }

    public function list(RegistryListQuery $input): array
    {
        $query = $this->em->getRepository(UnityProvider::class)->createQueryBuilder('p');

        if ('' !== trim($input->query)) {
            $query->andWhere('LOWER(p.name) LIKE :query')->setParameter('query', '%'.mb_strtolower(trim($input->query)).'%');
        }

        $items = array_map($this->present(...), $query->orderBy('p.name', 'ASC')->addOrderBy('p.id', 'ASC')->getQuery()->getResult());

        return ['items' => $items, 'total' => count($items), 'page' => 1, 'pageSize' => count($items)];
    }

    public function save(UnityProviderInput $input, ?UnityProvider $record = null): UnityProvider
    {
        $record ??= new UnityProvider(trim($input->name));
        $record->setName(trim($input->name));
        $this->em->persist($record);
        $this->em->flush();

        return $record;
    }

    public function remove(UnityProvider $record): void
    {
        $this->em->wrapInTransaction(function () use ($record): void {
            $this->em->refresh($record, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            SoftDeletionGuard::assertUnused($this->em, $record);
            $record->markDeleted();
            $this->em->flush();
        });
    }
}
