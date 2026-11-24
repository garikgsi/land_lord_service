<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Object-specific utility suppliers and optional supplier for an object meter; preserve legacy supplier versions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE object_unity_providers ADD unity_id INT');
        $this->addSql('UPDATE object_unity_providers op SET unity_id = p.unity_id FROM unity_providers p WHERE p.id = op.unity_provider_id');
        $this->addSql('ALTER TABLE object_unity_providers ALTER unity_id SET NOT NULL');
        $this->addSql('ALTER TABLE object_unity_providers ADD CONSTRAINT fk_object_provider_unity FOREIGN KEY (unity_id) REFERENCES unities (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX idx_object_unity_providers_unity_id ON object_unity_providers (unity_id)');
        $this->addSql('ALTER TABLE object_unity_providers ADD CONSTRAINT uq_object_provider_unity UNIQUE (object_id, unity_provider_id, unity_id)');

        // Keep the former global type only as legacy information, including archived versions.
        $this->addSql('ALTER TABLE unity_providers ALTER unity_id DROP NOT NULL');
        $this->addSql('ALTER TABLE unity_providers_history ALTER unity_id DROP NOT NULL');
        $this->addSql("COMMENT ON COLUMN unity_providers.unity_id IS 'Legacy global utility before object-specific suppliers; not used by the application'");
        $this->addSql('ALTER TABLE object_meters ADD object_unity_provider_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE object_meters ADD CONSTRAINT fk_object_meter_provider FOREIGN KEY (object_unity_provider_id) REFERENCES object_unity_providers (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX idx_object_meters_provider_id ON object_meters (object_unity_provider_id)');
        $this->addSql(<<<'SQL'
            CREATE FUNCTION check_object_meter_provider() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                provider_object INT;
                provider_unity INT;
                meter_unity INT;
            BEGIN
                IF TG_TABLE_NAME = 'meters' THEN
                    IF EXISTS (SELECT 1 FROM object_meters om JOIN object_unity_providers op ON op.id = om.object_unity_provider_id WHERE om.meter_id = NEW.id AND op.unity_id <> NEW.utility_id) THEN
                        RAISE EXCEPTION 'Meter utility conflicts with its supplier' USING ERRCODE = '23514';
                    END IF;
                ELSIF TG_TABLE_NAME = 'object_unity_providers' THEN
                    IF EXISTS (SELECT 1 FROM object_meters om JOIN meters m ON m.id = om.meter_id WHERE om.object_unity_provider_id = NEW.id AND (om.object_id <> NEW.object_id OR m.utility_id <> NEW.unity_id)) THEN
                        RAISE EXCEPTION 'Supplier conflicts with installed meters' USING ERRCODE = '23514';
                    END IF;
                ELSIF NEW.object_unity_provider_id IS NOT NULL THEN
                    SELECT object_id, unity_id INTO provider_object, provider_unity FROM object_unity_providers WHERE id = NEW.object_unity_provider_id FOR SHARE;
                    SELECT utility_id INTO meter_unity FROM meters WHERE id = NEW.meter_id;
                    IF provider_object IS NOT NULL AND (provider_object <> NEW.object_id OR provider_unity <> meter_unity) THEN
                        RAISE EXCEPTION 'Supplier must match the meter object and utility' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$
            SQL);
        $this->addSql('CREATE TRIGGER object_meter_provider_check BEFORE INSERT OR UPDATE ON object_meters FOR EACH ROW EXECUTE FUNCTION check_object_meter_provider()');
        $this->addSql('CREATE TRIGGER meter_provider_utility_check BEFORE UPDATE OF utility_id ON meters FOR EACH ROW EXECUTE FUNCTION check_object_meter_provider()');
        $this->addSql('CREATE TRIGGER object_provider_meter_check BEFORE UPDATE OF object_id, unity_id ON object_unity_providers FOR EACH ROW EXECUTE FUNCTION check_object_meter_provider()');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((bool) $this->connection->fetchOne('SELECT EXISTS (SELECT 1 FROM object_meters WHERE object_unity_provider_id IS NOT NULL)'), 'Cannot discard assigned meter suppliers; clear assignments before downgrading.');
        $this->abortIf((bool) $this->connection->fetchOne('SELECT EXISTS (SELECT unity_provider_id FROM object_unity_providers GROUP BY unity_provider_id HAVING COUNT(DISTINCT unity_id) > 1)'), 'Multiple supplier utilities cannot be represented by the old schema.');
        $this->abortIf((bool) $this->connection->fetchOne('SELECT EXISTS (SELECT 1 FROM unity_providers p WHERE p.unity_id IS NULL AND NOT EXISTS (SELECT 1 FROM object_unity_providers op WHERE op.unity_provider_id = p.id))'), 'A supplier without a utility cannot be represented by the old schema.');
        $this->addSql('DROP TRIGGER object_meter_provider_check ON object_meters');
        $this->addSql('DROP TRIGGER meter_provider_utility_check ON meters');
        $this->addSql('DROP TRIGGER object_provider_meter_check ON object_unity_providers');
        $this->addSql('DROP FUNCTION check_object_meter_provider()');
        $this->addSql('ALTER TABLE object_meters DROP object_unity_provider_id');
        $this->addSql('ALTER TABLE unity_providers DISABLE TRIGGER unity_providers_archive_version');
        $this->addSql('UPDATE unity_providers p SET unity_id = COALESCE((SELECT MIN(op.unity_id) FROM object_unity_providers op WHERE op.unity_provider_id = p.id), p.unity_id)');
        $this->addSql('ALTER TABLE unity_providers ENABLE TRIGGER unity_providers_archive_version');
        $this->addSql('ALTER TABLE unity_providers ALTER unity_id SET NOT NULL');
        $this->addSql('COMMENT ON COLUMN unity_providers.unity_id IS NULL');
        $this->addSql('ALTER TABLE object_unity_providers DROP unity_id');
        // Legacy history keeps NULL where the new era had no global utility; never invent past values.
    }
}
