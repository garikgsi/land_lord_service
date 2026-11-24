<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SoftDeletionGuard
{
    public static function assertUnused(EntityManagerInterface $em, object $record): void
    {
        $db = $em->getConnection();
        $table = $em->getClassMetadata($record::class)->getTableName();
        $links = $db->fetchAllAssociative("SELECT child.relname AS table_name, a.attname AS column_name FROM pg_constraint fk JOIN pg_class child ON child.oid=fk.conrelid JOIN pg_attribute a ON a.attrelid=fk.conrelid AND a.attnum=fk.conkey[1] WHERE fk.contype='f' AND fk.confrelid=to_regclass(?) AND cardinality(fk.conkey)=1", [$table]);
        foreach ($links as $link) {
            if (str_ends_with($link['table_name'], '_history')) { continue; }
            $marked = $db->fetchOne("SELECT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=current_schema() AND table_name=? AND column_name='is_deleted')", [$link['table_name']]);
            $query = 'SELECT EXISTS(SELECT 1 FROM '.$db->quoteSingleIdentifier($link['table_name']).' WHERE '.$db->quoteSingleIdentifier($link['column_name']).' = ?'.($marked ? ' AND NOT is_deleted' : '').')';
            if ($db->fetchOne($query, [$record->getId()])) { throw new ConflictHttpException('Запись используется другими данными. Сначала проверьте её связи.'); }
        }
    }
}
