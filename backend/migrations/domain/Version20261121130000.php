<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121130000 extends AbstractMigration
{
    private const TABLES = ['ownerships', 'contract_types', 'renter_contacts'];

    public function getDescription(): string
    {
        return 'Full version snapshots for editable catalogues and renter contacts';
    }

    public function up(Schema $schema): void
    {
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
    }
}
