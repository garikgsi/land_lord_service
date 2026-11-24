<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Irreversible contract signing, unversioned drafts and signed-only accruals';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contracts ADD is_signed BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE contracts_history ADD is_signed BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION archive_entity_version() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                changed_at TIMESTAMP(6) WITHOUT TIME ZONE;
                source_columns TEXT;
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    NEW.valid_from := clock_timestamp() AT TIME ZONE 'UTC';
                    RETURN NEW;
                END IF;
                IF TG_TABLE_NAME = 'contracts' AND (to_jsonb(OLD)->>'is_signed')::boolean
                   AND NOT (to_jsonb(NEW)->>'is_signed')::boolean THEN
                    RAISE EXCEPTION 'Contract signing cannot be revoked' USING ERRCODE = '23514';
                END IF;
                IF (to_jsonb(NEW) - 'valid_from') IS NOT DISTINCT FROM (to_jsonb(OLD) - 'valid_from') THEN
                    NEW.valid_from := OLD.valid_from;
                    RETURN NEW;
                END IF;
                changed_at := GREATEST(clock_timestamp() AT TIME ZONE 'UTC', OLD.valid_from + INTERVAL '1 microsecond');
                IF TG_TABLE_NAME = 'contracts' AND NOT (to_jsonb(OLD)->>'is_signed')::boolean THEN
                    NEW.valid_from := changed_at;
                    RETURN NEW;
                END IF;
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
        $this->addSql(<<<'SQL'
            CREATE FUNCTION require_signed_accrual_contract() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                PERFORM 1 FROM contracts WHERE id = NEW.contract_id AND is_signed FOR SHARE;
                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Unsigned contracts cannot receive accruals' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$
            SQL);
        $this->addSql('CREATE TRIGGER contract_accruals_require_signed BEFORE INSERT OR UPDATE OF contract_id ON contract_accruals FOR EACH ROW EXECUTE FUNCTION require_signed_accrual_contract()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER contract_accruals_require_signed ON contract_accruals');
        $this->addSql('DROP FUNCTION require_signed_accrual_contract()');
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION archive_entity_version() RETURNS trigger LANGUAGE plpgsql AS $$
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
        $this->addSql('ALTER TABLE contracts_history DROP is_signed');
        $this->addSql('ALTER TABLE contracts DROP is_signed');
    }
}
