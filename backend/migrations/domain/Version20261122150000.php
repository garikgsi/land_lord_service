<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Align utility contract junction index names with ORM mapping'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_unity_provider_contract_unities_contract RENAME TO idx_ad807bea2576e0fd');
        $this->addSql('ALTER INDEX idx_unity_provider_contract_unities_unity RENAME TO idx_ad807beaf6859c8c');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_ad807bea2576e0fd RENAME TO idx_unity_provider_contract_unities_contract');
        $this->addSql('ALTER INDEX idx_ad807beaf6859c8c RENAME TO idx_unity_provider_contract_unities_unity');
    }
}
