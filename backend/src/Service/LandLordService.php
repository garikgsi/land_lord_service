<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\LandLordInput;
use App\Dto\RegistryListQuery;
use App\Entity\LandLord;
use Doctrine\ORM\EntityManagerInterface;

final readonly class LandLordService
{
    private const PAGE_SIZE = 20;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(int $id): ?LandLord
    {
        return $this->em->find(LandLord::class, $id);
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
        return ['id' => $record->getId(), 'name' => $record->getName(), 'color' => $record->getColor()];
    }

    public function save(LandLordInput $input, ?LandLord $record = null): LandLord
    {
        $record ??= new LandLord(trim($input->name));
        $record->setName(trim($input->name));
        $record->setColor($input->color);
        $this->em->persist($record);
        $this->em->flush();

        return $record;
    }
}
