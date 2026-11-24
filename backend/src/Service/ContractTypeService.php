<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\NamedRecordInput;
use App\Dto\RegistryListQuery;
use App\Entity\ContractType;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ContractTypeService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(int $id): ?ContractType
    {
        return $this->em->getRepository(ContractType::class)->find($id);
    }

    public function present(ContractType $record): array
    {
        return ['id' => $record->getId(), 'name' => $record->getName()];
    }

    public function list(RegistryListQuery $input): array
    {
        $query = $this->em->getRepository(ContractType::class)->createQueryBuilder('r');
        if ('' !== trim($input->query)) {
            $query->andWhere('LOWER(r.name) LIKE :query')->setParameter('query', '%'.mb_strtolower(trim($input->query)).'%');
        }
        $items = array_map($this->present(...), $query->orderBy('r.id', 'ASC')->getQuery()->getResult());

        return ['items' => $items, 'total' => count($items), 'page' => 1, 'pageSize' => count($items)];
    }

    public function save(NamedRecordInput $input, ?ContractType $record = null): ContractType
    {
        $record ??= new ContractType(trim($input->name));
        $record->setName(trim($input->name));
        $this->em->persist($record);
        $this->em->flush();

        return $record;
    }

    public function remove(ContractType $record): void
    {
        $this->em->wrapInTransaction(function () use ($record): void {
            $this->em->refresh($record, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            if (1 === $record->getId()) { throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('Тип «аренда» используется по умолчанию и не удаляется.'); }
            SoftDeletionGuard::assertUnused($this->em, $record);
            $record->markDeleted();
            $this->em->flush();
        });
    }
}
