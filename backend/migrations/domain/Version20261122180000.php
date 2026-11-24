<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Weekly unsigned rental contract notification settings for lawyers and directors';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO notification_types (id, name, days, roles, is_active)
            VALUES (5, 'Начавшийся неподписанный договор аренды', 7, '["ROLE_LAWYER","ROLE_DIRECTOR"]', true)
            SQL);
        $this->addSql('ALTER TABLE notification_types ADD CONSTRAINT chk_unsigned_contract_notification_interval CHECK (id <> 5 OR days > 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_types DROP CONSTRAINT chk_unsigned_contract_notification_interval');
        $this->addSql('DELETE FROM notification_types WHERE id = 5');
    }
}
