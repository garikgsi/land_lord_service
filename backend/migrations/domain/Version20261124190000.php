<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Assign an optional meter explicitly to each utility provider contract';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unity_provider_contracts ADD meter_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE unity_provider_contracts_history ADD meter_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE unity_provider_contracts ADD CONSTRAINT fk_provider_contract_meter FOREIGN KEY (meter_id) REFERENCES meters (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX idx_provider_contract_meter ON unity_provider_contracts (meter_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unity_provider_contracts DROP CONSTRAINT fk_provider_contract_meter');
        $this->addSql('DROP INDEX idx_provider_contract_meter');
        $this->addSql('ALTER TABLE unity_provider_contracts DROP meter_id');
        $this->addSql('ALTER TABLE unity_provider_contracts_history DROP meter_id');
    }
}
