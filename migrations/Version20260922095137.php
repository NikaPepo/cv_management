<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922095137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add version column to position for optimistic locking (multiple recruiters/admins may edit the same position).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE position ADD COLUMN version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE position DROP COLUMN version');
    }
}
