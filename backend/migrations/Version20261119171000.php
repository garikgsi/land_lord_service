<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119171000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the initial system account and reserve super administrator for this account';
    }

    public function up(Schema $schema): void
    {
        // Preserve the existing technical account and its credentials on upgrades.
        $this->addSql(<<<'SQL'
            INSERT INTO users (email, password, roles, blocked, active, createdAt)
            VALUES ('admin@example.com', '', '["ROLE_ADMIN", "ROLE_SUPER_ADMIN"]', false, false, CURRENT_TIMESTAMP)
            ON CONFLICT (email) DO NOTHING
            SQL);
        foreach (['access_tokens', 'password_resets', 'user_invitations'] as $table) {
            $this->addSql("DELETE FROM $table WHERE user_id IN (SELECT id FROM users WHERE email <> 'admin@example.com' AND roles::jsonb @> '[\"ROLE_SUPER_ADMIN\"]'::jsonb)");
        }
        $this->addSql("UPDATE users SET roles = (roles::jsonb - 'ROLE_SUPER_ADMIN')::json WHERE email <> 'admin@example.com' AND roles::jsonb @> '[\"ROLE_SUPER_ADMIN\"]'::jsonb");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The initial system account and existing credentials must be preserved.');
    }
}
