<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class RegistryRepository extends EntityRepository
{
    private function active(array $criteria): array
    {
        if ($this->getClassMetadata()->hasField('isDeleted')) {
            $criteria['isDeleted'] = false;
        }

        return $criteria;
    }

    public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        $query = parent::createQueryBuilder($alias, $indexBy);
        if ($this->getClassMetadata()->hasField('isDeleted')) {
            $query->andWhere($alias.'.isDeleted = false');
        }

        return $query;
    }

    public function find(mixed $id, LockMode|int|null $lockMode = LockMode::NONE, ?int $lockVersion = null): ?object
    {
        $record = parent::find($id, $lockMode, $lockVersion);

        return null !== $record && method_exists($record, 'isDeleted') && $record->isDeleted() ? null : $record;
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return parent::findBy($this->active($criteria), $orderBy, $limit, $offset);
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        return parent::findOneBy($this->active($criteria), $orderBy);
    }

    public function count(array $criteria = []): int
    {
        return parent::count($this->active($criteria));
    }
}
