<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122190000 extends AbstractMigration
{
    public function getDescription(): string { return 'Retained registry deletion markers and unsigned contract removal'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE land_lords ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('land_lords_history')")) {
            $this->addSql('ALTER TABLE land_lords_history ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        }
        $this->addSql('ALTER TABLE objects ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('objects_history')")) {
            $this->addSql('ALTER TABLE objects_history ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        }
        $this->addSql('ALTER TABLE renters ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('renters_history')")) {
            $this->addSql('ALTER TABLE renters_history ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        }
        $this->addSql('ALTER TABLE ownerships ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('ownerships_history')")) {
            $this->addSql('ALTER TABLE ownerships_history ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        }
        $this->addSql('ALTER TABLE contract_types ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('contract_types_history')")) {
            $this->addSql('ALTER TABLE contract_types_history ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        }
        $this->addSql('ALTER TABLE unities ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('unities_history')")) {
            $this->addSql('ALTER TABLE unities_history ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        }
        $this->addSql('ALTER TABLE unity_providers ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('unity_providers_history')")) {
            $this->addSql('ALTER TABLE unity_providers_history ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        }
        $this->addSql('ALTER TABLE contracts ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('contracts_history')")) {
            $this->addSql('ALTER TABLE contracts_history ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        }
        $this->addSql('ALTER TABLE spaces ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('spaces_history')")) {
            $this->addSql('ALTER TABLE spaces_history ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        }
        $this->addSql('ALTER TABLE meters ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('meters_history')")) {
            $this->addSql('ALTER TABLE meters_history ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        }
        $this->addSql('ALTER TABLE unity_provider_contracts ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('unity_provider_contracts_history')")) {
            $this->addSql('ALTER TABLE unity_provider_contracts_history ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        }
        $this->addSql('ALTER TABLE renter_contacts ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('renter_contacts_history')")) {
            $this->addSql('ALTER TABLE renter_contacts_history ADD is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        }
        $this->addSql('ALTER TABLE contracts DROP CONSTRAINT ex_contracts_space_period');
        $this->addSql(<<<'SQL'
            ALTER TABLE contracts ADD CONSTRAINT ex_contracts_space_period EXCLUDE USING gist (
                space_id WITH =,
                (CASE
                    WHEN LEAST(expiry_date, annulment_date) < contract_date THEN 'empty'::daterange
                    ELSE daterange(contract_date, LEAST(expiry_date, annulment_date), '[]')
                END) WITH &&
            ) WHERE (NOT is_deleted)
            SQL);
        $this->addSql('ALTER TABLE contracts ADD CONSTRAINT chk_signed_contract_not_deleted CHECK (NOT (is_signed AND is_deleted))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contracts DROP CONSTRAINT chk_signed_contract_not_deleted');
        $this->addSql('ALTER TABLE contracts DROP CONSTRAINT ex_contracts_space_period');
        $this->addSql(<<<'SQL'
            ALTER TABLE contracts ADD CONSTRAINT ex_contracts_space_period EXCLUDE USING gist (
                space_id WITH =,
                (CASE
                    WHEN LEAST(expiry_date, annulment_date) < contract_date THEN 'empty'::daterange
                    ELSE daterange(contract_date, LEAST(expiry_date, annulment_date), '[]')
                END) WITH &&
            )
            SQL);
        if (null !== $this->connection->fetchOne("SELECT to_regclass('renter_contacts_history')")) {
            $this->addSql('ALTER TABLE renter_contacts_history DROP is_deleted');
        }
        $this->addSql('ALTER TABLE renter_contacts DROP is_deleted');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('unity_provider_contracts_history')")) {
            $this->addSql('ALTER TABLE unity_provider_contracts_history DROP is_deleted');
        }
        $this->addSql('ALTER TABLE unity_provider_contracts DROP is_deleted');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('meters_history')")) {
            $this->addSql('ALTER TABLE meters_history DROP is_deleted');
        }
        $this->addSql('ALTER TABLE meters DROP is_deleted');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('spaces_history')")) {
            $this->addSql('ALTER TABLE spaces_history DROP is_deleted');
        }
        $this->addSql('ALTER TABLE spaces DROP is_deleted');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('contracts_history')")) {
            $this->addSql('ALTER TABLE contracts_history DROP is_deleted');
        }
        $this->addSql('ALTER TABLE contracts DROP is_deleted');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('unity_providers_history')")) {
            $this->addSql('ALTER TABLE unity_providers_history DROP is_deleted');
        }
        $this->addSql('ALTER TABLE unity_providers DROP is_deleted');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('unities_history')")) {
            $this->addSql('ALTER TABLE unities_history DROP is_deleted');
        }
        $this->addSql('ALTER TABLE unities DROP is_deleted');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('contract_types_history')")) {
            $this->addSql('ALTER TABLE contract_types_history DROP is_deleted');
        }
        $this->addSql('ALTER TABLE contract_types DROP is_deleted');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('ownerships_history')")) {
            $this->addSql('ALTER TABLE ownerships_history DROP is_deleted');
        }
        $this->addSql('ALTER TABLE ownerships DROP is_deleted');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('renters_history')")) {
            $this->addSql('ALTER TABLE renters_history DROP is_deleted');
        }
        $this->addSql('ALTER TABLE renters DROP is_deleted');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('objects_history')")) {
            $this->addSql('ALTER TABLE objects_history DROP is_deleted');
        }
        $this->addSql('ALTER TABLE objects DROP is_deleted');
        if (null !== $this->connection->fetchOne("SELECT to_regclass('land_lords_history')")) {
            $this->addSql('ALTER TABLE land_lords_history DROP is_deleted');
        }
        $this->addSql('ALTER TABLE land_lords DROP is_deleted');
    }
}
