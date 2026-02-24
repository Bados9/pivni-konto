<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create group_achievement table for group awards tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE group_achievement (
            id UUID NOT NULL,
            group_id UUID NOT NULL,
            user_id UUID NOT NULL,
            type VARCHAR(30) NOT NULL,
            date DATE NOT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN group_achievement.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN group_achievement.group_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN group_achievement.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN group_achievement.date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN group_achievement.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX unique_group_achievement_date ON group_achievement (group_id, type, date)');
        $this->addSql('CREATE INDEX idx_group_achievement_group_user ON group_achievement (group_id, user_id)');
        $this->addSql('ALTER TABLE group_achievement ADD CONSTRAINT FK_group_achievement_group FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE group_achievement ADD CONSTRAINT FK_group_achievement_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE group_achievement');
    }
}
