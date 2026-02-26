<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add default_beer_id to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD default_beer_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN "user".default_beer_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_user_default_beer FOREIGN KEY (default_beer_id) REFERENCES beer (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_user_default_beer ON "user" (default_beer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_user_default_beer');
        $this->addSql('DROP INDEX IDX_user_default_beer');
        $this->addSql('ALTER TABLE "user" DROP default_beer_id');
    }
}
