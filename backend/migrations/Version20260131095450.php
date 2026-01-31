<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260131095450 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_user_consumed ON beer_entry (user_id, consumed_at)');
        $this->addSql('CREATE INDEX idx_group_consumed ON beer_entry (group_id, consumed_at)');
        $this->addSql('ALTER TABLE "group" ALTER invite_code TYPE VARCHAR(16)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_user_consumed');
        $this->addSql('DROP INDEX idx_group_consumed');
        $this->addSql('ALTER TABLE "group" ALTER invite_code TYPE VARCHAR(8)');
    }
}
