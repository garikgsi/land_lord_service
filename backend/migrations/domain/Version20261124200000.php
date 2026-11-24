<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional meter model and comment to current and historical versions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE meters ADD model TEXT DEFAULT NULL, ADD comment TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE meters_history ADD model TEXT DEFAULT NULL, ADD comment TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE meters DROP model, DROP comment');
        $this->addSql('ALTER TABLE meters_history DROP model, DROP comment');
    }
}
