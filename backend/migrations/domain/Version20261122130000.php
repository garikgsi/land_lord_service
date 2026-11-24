<?php

declare(strict_types=1);

namespace DomainMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Configurable notification types, lead days and recipient roles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE notification_types (
                id INT PRIMARY KEY,
                name TEXT NOT NULL CHECK (btrim(name) <> ''),
                days INT NOT NULL CHECK (days >= 0),
                roles JSONB NOT NULL CHECK (jsonb_typeof(roles) = 'array'),
                is_active BOOLEAN NOT NULL
            )
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO notification_types (id, name, days, roles, is_active) VALUES
              (1, 'Окончание договора аренды', 30, '["ROLE_LAWYER"]', true),
              (2, 'Индексация договора аренды', 30, '["ROLE_LAWYER"]', true),
              (3, 'Передача показаний счетчиков', 7, '["ROLE_TECHNICIAN"]', true),
              (4, 'Окончание договора коммунального поставщика', 30, '["ROLE_LAWYER"]', true)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_types');
    }
}
