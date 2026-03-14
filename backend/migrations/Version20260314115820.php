<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260314115820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE beer ADD status VARCHAR(20) NOT NULL DEFAULT \'approved\'');
        $this->addSql('ALTER TABLE beer ADD submitted_by_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE beer ADD CONSTRAINT FK_58F666AD79F7D87D FOREIGN KEY (submitted_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_58F666AD79F7D87D ON beer (submitted_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE beer DROP CONSTRAINT FK_58F666AD79F7D87D');
        $this->addSql('DROP INDEX IDX_58F666AD79F7D87D');
        $this->addSql('ALTER TABLE beer DROP status');
        $this->addSql('ALTER TABLE beer DROP submitted_by_id');
    }
}
