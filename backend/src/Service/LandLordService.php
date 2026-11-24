<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\LandLordInput;
use App\Dto\RegistryListQuery;
use App\Entity\LandLord;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class LandLordService
{
    private const PAGE_SIZE = 20;

    public function __construct(private EntityManagerInterface $em, private EntityColorService $colors)
    {
    }

    public function find(int $id): ?LandLord
    {
        return $this->em->getRepository(LandLord::class)->find($id);
    }

    public function list(RegistryListQuery $input): array
    {
        $query = $this->em->getRepository(LandLord::class)->createQueryBuilder('l');
        if ('' !== trim($input->query)) {
            $query->andWhere('LOWER(l.name) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower(trim($input->query)).'%');
        }

        $total = (int) (clone $query)->select('COUNT(l.id)')->getQuery()->getSingleScalarResult();
        $records = $query->orderBy('l.name', 'ASC')->addOrderBy('l.id', 'ASC')
            ->setFirstResult(($input->page - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE)->getQuery()->getResult();

        return ['items' => array_map($this->present(...), $records), 'total' => $total, 'page' => $input->page, 'pageSize' => self::PAGE_SIZE];
    }

    public function present(LandLord $record): array
    {
        return ['id' => $record->getId(), 'name' => $record->getName(), 'color' => $this->colors->color($record)];
    }

    public function save(LandLordInput $input, ?LandLord $record = null): LandLord
    {
        return $this->em->wrapInTransaction(function () use ($input, $record): LandLord {
            if (null !== $record) {
                $this->em->refresh($record, LockMode::PESSIMISTIC_WRITE);
            }
            $record ??= new LandLord(trim($input->name));
            $record->setName(trim($input->name));
            $this->em->persist($record);
            $this->em->flush();
            $this->colors->save($record, $input->color);

            return $record;
        });
    }

    public function remove(LandLord $record): void
    {
        $this->em->wrapInTransaction(function () use ($record): void {
            $this->em->refresh($record, LockMode::PESSIMISTIC_WRITE);
            SoftDeletionGuard::assertUnused($this->em, $record);
            $record->markDeleted();
            $this->em->flush();
        });
    }
}
