<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122160000 extends AbstractMigration
{
    public function getDescription(): string { return 'Preserve utility membership in supplier contract versions'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unity_provider_contracts ADD unity_ids JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE unity_provider_contracts_history ADD unity_ids JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unity_provider_contracts_history DROP unity_ids');
        $this->addSql('ALTER TABLE unity_provider_contracts DROP unity_ids');
    }
}
