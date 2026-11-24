<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\VersionedEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EntityHistoryService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function versions(VersionedEntity $entity): array
    {
        $metadata = $this->em->getClassMetadata($entity::class);
        $id = $metadata->getIdentifierValues($entity)['id'];
        $table = $this->em->getConnection()->quoteSingleIdentifier($metadata->getTableName());
        $archive = $this->em->getConnection()->quoteSingleIdentifier($metadata->getTableName().'_history');

        $connection = $this->em->getConnection();
        $columns = implode(', ', array_map($connection->quoteSingleIdentifier(...), $connection->fetchFirstColumn(
            'SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? ORDER BY ordinal_position',
            [$metadata->getTableName()],
        )));

        return $connection->fetchAllAssociative(
            'SELECT '.$columns.', valid_to FROM '.$archive.' WHERE id = ? UNION ALL SELECT '.$columns.', NULL::timestamp AS valid_to FROM '.$table.' WHERE id = ? ORDER BY valid_to ASC NULLS LAST',
            [$id, $id],
        );
    }

    public function stateAt(string $class, int $id, \DateTimeImmutable $moment): ?array
    {
        if (!is_a($class, VersionedEntity::class, true)) {
            throw new \InvalidArgumentException('The entity does not support version history.');
        }
        $metadata = $this->em->getClassMetadata($class);
        $table = $this->em->getConnection()->quoteSingleIdentifier($metadata->getTableName());
        $archive = $this->em->getConnection()->quoteSingleIdentifier($metadata->getTableName().'_history');
        $timestamp = $moment->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $current = $this->em->getConnection()->fetchAssociative('SELECT * FROM '.$table.' WHERE id = ? AND (valid_from IS NULL OR valid_from <= ?)', [$id, $timestamp]);
        if (false !== $current) {
            return $current;
        }
        $past = $this->em->getConnection()->fetchAssociative('SELECT * FROM '.$archive.' WHERE id = ? AND (valid_from IS NULL OR valid_from <= ?) AND valid_to > ? ORDER BY valid_to LIMIT 1', [$id, $timestamp, $timestamp]);

        return false === $past ? null : $past;
    }
}
