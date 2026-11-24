<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121160000 extends AbstractMigration
{
    private const TABLES = ['unities', 'unity_providers', 'meters'];

    public function getDescription(): string
    {
        return 'Versioned utility catalogues and meters, preserving existing verification dates';
    }

    public function up(Schema $schema): void
    {
        // Preserve the legacy last verification as an actual history entry.
        $this->addSql('INSERT INTO meter_verifications (meter_id, verification_date) SELECT id, verification_date FROM meters m WHERE verification_date IS NOT NULL AND NOT EXISTS (SELECT 1 FROM meter_verifications v WHERE v.meter_id = m.id AND v.verification_date = m.verification_date)');
        $this->addSql('UPDATE meters m SET verification_date = (SELECT MAX(v.verification_date) FROM meter_verifications v WHERE v.meter_id = m.id) WHERE EXISTS (SELECT 1 FROM meter_verifications v WHERE v.meter_id = m.id)');
        foreach (self::TABLES as $table) {
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
        // Verification dates are retained; rolling back versioning must not erase known history.
    }
}
