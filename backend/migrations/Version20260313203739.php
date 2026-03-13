<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260313203739 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE push_subscription (id UUID NOT NULL, endpoint VARCHAR(500) NOT NULL, auth_key VARCHAR(255) NOT NULL, auth_token VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_562830F3A76ED395 ON push_subscription (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_push_user_endpoint ON push_subscription (user_id, endpoint)');
        $this->addSql('ALTER TABLE push_subscription ADD CONSTRAINT FK_562830F3A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER INDEX uniq_9bace7e1_token RENAME TO UNIQ_9BACE7E15F37A13B');
        $this->addSql('COMMENT ON COLUMN "user".default_beer_id IS \'\'');
        $this->addSql('ALTER INDEX idx_user_default_beer RENAME TO IDX_8D93D649221DC116');
        $this->addSql('COMMENT ON COLUMN user_achievement.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN user_achievement.user_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN user_achievement.unlocked_at IS \'\'');
        $this->addSql('ALTER INDEX idx_user_achievement_user RENAME TO IDX_3F68B664A76ED395');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE push_subscription DROP CONSTRAINT FK_562830F3A76ED395');
        $this->addSql('DROP TABLE push_subscription');
        $this->addSql('ALTER INDEX uniq_9bace7e15f37a13b RENAME TO uniq_9bace7e1_token');
        $this->addSql('COMMENT ON COLUMN "user".default_beer_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER INDEX idx_8d93d649221dc116 RENAME TO idx_user_default_beer');
        $this->addSql('COMMENT ON COLUMN user_achievement.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN user_achievement.unlocked_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_achievement.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER INDEX idx_3f68b664a76ed395 RENAME TO idx_user_achievement_user');
    }
}
