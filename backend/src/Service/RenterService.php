<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\RegistryListQuery;
use App\Dto\RenterInput;
use App\Entity\Renter;
use App\Exception\InputException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RenterService
{
    public function __construct(private EntityManagerInterface $em, private OwnershipService $ownerships)
    {
    }

    public function find(int $id): ?Renter
    {
        return $this->em->getRepository(Renter::class)->find($id);
    }

    public function present(Renter $record): array
    {
        return ['id' => $record->getId(), 'name' => $record->getName(), 'inn' => $record->getInn(), 'ownership' => $this->ownerships->present($record->getOwnership())];
    }

    public function list(RegistryListQuery $input): array
    {
        $query = $this->em->getRepository(Renter::class)->createQueryBuilder('r')->addSelect('o')->join('r.ownership', 'o');
        if ('' !== trim($input->query)) {
            $query->andWhere('LOWER(r.name) LIKE :query OR r.inn LIKE :query')->setParameter('query', '%'.mb_strtolower(trim($input->query)).'%');
        }
        $total = (int) (clone $query)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        $records = $query->orderBy('r.name', 'ASC')->addOrderBy('r.id', 'ASC')->setFirstResult(($input->page - 1) * 20)->setMaxResults(20)->getQuery()->getResult();

        return ['items' => array_map($this->present(...), $records), 'total' => $total, 'page' => $input->page, 'pageSize' => 20];
    }

    public function save(RenterInput $input, ?Renter $record = null): Renter
    {
        $ownership = $this->ownerships->find($input->ownershipId) ?? throw new InputException('ownershipId', 'Выбранная форма собственности не найдена.');
        $inn = null === $input->inn || '' === trim($input->inn) ? null : trim($input->inn);
        if (null !== $inn) {
            $existing = $this->em->getRepository(Renter::class)->findOneBy(['inn' => $inn]);
            if (null !== $existing && $existing->getId() !== $record?->getId()) {
                throw new InputException('inn', 'Арендатор с таким ИНН уже существует.');
            }
        }
        $record ??= new Renter($ownership, trim($input->name));
        $record->setName(trim($input->name));
        $record->setOwnership($ownership);
        $record->setInn($inn);
        $this->em->persist($record);
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new InputException('inn', 'Арендатор с таким ИНН уже существует.');
        }

        return $record;
    }

    public function remove(Renter $record): void
    {
        $this->em->wrapInTransaction(function () use ($record): void {
            $this->em->refresh($record, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            SoftDeletionGuard::assertUnused($this->em, $record);
            $record->markDeleted();
            $this->em->flush();
        });
    }
}
