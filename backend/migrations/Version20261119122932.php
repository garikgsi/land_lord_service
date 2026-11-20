<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119122932 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional first and last names for user avatar initials';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD firstName VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD lastName VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP firstName');
        $this->addSql('ALTER TABLE users DROP lastName');
    }
}
