<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260131223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_achievement table for tracking unlocked achievements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_achievement (
            id UUID NOT NULL,
            user_id UUID NOT NULL,
            achievement_id VARCHAR(50) NOT NULL,
            unlocked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX unique_user_achievement ON user_achievement (user_id, achievement_id)');
        $this->addSql('CREATE INDEX idx_user_achievement_user ON user_achievement (user_id)');
        $this->addSql('COMMENT ON COLUMN user_achievement.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN user_achievement.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN user_achievement.unlocked_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_achievement ADD CONSTRAINT FK_3F68B664A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_achievement');
    }
}
