<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove meter attribute versioning, retaining current meters, readings and verifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS meters_archive_version ON meters');
        $this->addSql('DROP TABLE meters_history');
        $this->addSql('ALTER TABLE meters DROP valid_from');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Removed meter attribute history cannot be reconstructed.');
    }
}
