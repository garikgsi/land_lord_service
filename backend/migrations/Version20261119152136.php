<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119152136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grant super administrator to the agreed technical account and revoke its old sessions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE users SET roles = (roles::jsonb || '["ROLE_SUPER_ADMIN"]'::jsonb)::json
            WHERE email = 'admin@example.com' AND NOT roles::jsonb @> '["ROLE_SUPER_ADMIN"]'::jsonb
            SQL);
        foreach (['access_tokens', 'password_resets', 'user_invitations'] as $table) {
            $this->addSql("DELETE FROM $table WHERE user_id IN (SELECT id FROM users WHERE email = 'admin@example.com')");
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE users SET roles = (roles::jsonb - 'ROLE_SUPER_ADMIN')::json WHERE email = 'admin@example.com'");
        foreach (['access_tokens', 'password_resets', 'user_invitations'] as $table) {
            $this->addSql("DELETE FROM $table WHERE user_id IN (SELECT id FROM users WHERE email = 'admin@example.com')");
        }
    }
}
