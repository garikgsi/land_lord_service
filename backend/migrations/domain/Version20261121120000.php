<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121120000 extends AbstractMigration
{
    private const TABLES = ['land_lords', 'objects', 'renters', 'spaces', 'contracts'];

    public function getDescription(): string
    {
        return 'Current validity, full archived snapshots and optional indexation reminders';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'PostgreSQL is required.');
        $this->addSql('ALTER TABLE contracts ADD increase_interval_months INT DEFAULT NULL CHECK (increase_interval_months > 0)');
        $this->addSql(<<<'SQL'
            CREATE FUNCTION archive_entity_version() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                changed_at TIMESTAMP(6) WITHOUT TIME ZONE;
                source_columns TEXT;
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    NEW.valid_from := clock_timestamp() AT TIME ZONE 'UTC';
                    RETURN NEW;
                END IF;
                IF (to_jsonb(NEW) - 'valid_from') IS NOT DISTINCT FROM (to_jsonb(OLD) - 'valid_from') THEN
                    NEW.valid_from := OLD.valid_from;
                    RETURN NEW;
                END IF;
                changed_at := GREATEST(clock_timestamp() AT TIME ZONE 'UTC', OLD.valid_from + INTERVAL '1 microsecond');
                SELECT string_agg(quote_ident(attname), ', ' ORDER BY attnum)
                  INTO source_columns FROM pg_attribute
                 WHERE attrelid = TG_RELID AND attnum > 0 AND NOT attisdropped;
                EXECUTE format('INSERT INTO %I.%I (%s, valid_to) SELECT ($1).*, $2',
                    TG_TABLE_SCHEMA, TG_TABLE_NAME || '_history', source_columns)
                    USING OLD, changed_at;
                NEW.valid_from := changed_at;
                RETURN NEW;
            END;
            $$
            SQL);
        foreach (self::TABLES as $table) {
            // Legacy creation times are unknown; NULL is an open, unknown lower boundary.
            $this->addSql('ALTER TABLE '.$table.' ADD valid_from TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL');
            $this->addSql('CREATE TABLE '.$table.'_history (LIKE '.$table.')');
            $this->addSql('ALTER TABLE '.$table.'_history ADD valid_to TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL');
            $this->addSql('ALTER TABLE '.$table.'_history ADD PRIMARY KEY (id, valid_to)');
            $this->addSql('ALTER TABLE '.$table.'_history ADD FOREIGN KEY (id) REFERENCES '.$table.' (id) ON DELETE RESTRICT');
            $this->addSql('ALTER TABLE '.$table.'_history ADD CHECK (valid_from IS NULL OR valid_to > valid_from)');
            $this->addSql('CREATE TRIGGER '.$table.'_archive_version BEFORE INSERT OR UPDATE ON '.$table.' FOR EACH ROW EXECUTE FUNCTION archive_entity_version()');
        }
    }

    public function down(Schema $schema): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            $this->addSql('DROP TRIGGER '.$table.'_archive_version ON '.$table);
            $this->addSql('DROP TABLE '.$table.'_history');
            $this->addSql('ALTER TABLE '.$table.' DROP valid_from');
        }
        $this->addSql('DROP FUNCTION archive_entity_version()');
        $this->addSql('ALTER TABLE contracts DROP increase_interval_months');
    }
}
