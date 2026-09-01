<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notification table for per-user in-app notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification (id UUID NOT NULL, user_id UUID NOT NULL, type VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, message VARCHAR(500) NOT NULL, data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_notification_user_read ON notification (user_id, read_at)');
        $this->addSql('CREATE INDEX idx_notification_user_created ON notification (user_id, created_at)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CAA76ED395');
        $this->addSql('DROP TABLE notification');
    }
}
