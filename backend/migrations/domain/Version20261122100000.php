<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record the date of actually performed indexation in current and archived contracts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contracts ADD last_indexation_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE contracts_history ADD last_indexation_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contracts_history DROP last_indexation_date');
        $this->addSql('ALTER TABLE contracts DROP last_indexation_date');
    }
}
