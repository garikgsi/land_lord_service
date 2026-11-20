<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optional display colour for landlords';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'PostgreSQL is required.');

        $this->addSql('ALTER TABLE land_lords ADD color VARCHAR(7) DEFAULT NULL');
        $this->addSql("ALTER TABLE land_lords ADD CONSTRAINT chk_land_lords_color CHECK (color IS NULL OR color ~ '^#[0-9a-f]{6}$')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE land_lords DROP CONSTRAINT chk_land_lords_color');
        $this->addSql('ALTER TABLE land_lords DROP color');
    }
}
