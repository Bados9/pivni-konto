<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add times_unlocked column to user_achievement for repeatable achievements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_achievement ADD times_unlocked INT NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_achievement DROP COLUMN times_unlocked');
    }
}
