<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129165045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE beer (id UUID NOT NULL, name VARCHAR(100) NOT NULL, brewery VARCHAR(100) DEFAULT NULL, style VARCHAR(50) DEFAULT NULL, abv DOUBLE PRECISION DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE beer_entry (id UUID NOT NULL, custom_beer_name VARCHAR(100) DEFAULT NULL, quantity INT NOT NULL, volume_ml INT NOT NULL, consumed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, note VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, group_id UUID NOT NULL, beer_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D34BC742A76ED395 ON beer_entry (user_id)');
        $this->addSql('CREATE INDEX IDX_D34BC742FE54D947 ON beer_entry (group_id)');
        $this->addSql('CREATE INDEX IDX_D34BC742D0989053 ON beer_entry (beer_id)');
        $this->addSql('CREATE TABLE "group" (id UUID NOT NULL, name VARCHAR(100) NOT NULL, invite_code VARCHAR(8) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6DC044C56F21F112 ON "group" (invite_code)');
        $this->addSql('CREATE INDEX IDX_6DC044C5B03A8386 ON "group" (created_by_id)');
        $this->addSql('CREATE TABLE group_member (id UUID NOT NULL, role VARCHAR(20) NOT NULL, joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, group_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A36222A8A76ED395 ON group_member (user_id)');
        $this->addSql('CREATE INDEX IDX_A36222A8FE54D947 ON group_member (group_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_group_member ON group_member (group_id, user_id)');
        $this->addSql('CREATE TABLE "user" (id UUID NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('ALTER TABLE beer_entry ADD CONSTRAINT FK_D34BC742A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE beer_entry ADD CONSTRAINT FK_D34BC742FE54D947 FOREIGN KEY (group_id) REFERENCES "group" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE beer_entry ADD CONSTRAINT FK_D34BC742D0989053 FOREIGN KEY (beer_id) REFERENCES beer (id)');
        $this->addSql('ALTER TABLE "group" ADD CONSTRAINT FK_6DC044C5B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE group_member ADD CONSTRAINT FK_A36222A8A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE group_member ADD CONSTRAINT FK_A36222A8FE54D947 FOREIGN KEY (group_id) REFERENCES "group" (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE beer_entry DROP CONSTRAINT FK_D34BC742A76ED395');
        $this->addSql('ALTER TABLE beer_entry DROP CONSTRAINT FK_D34BC742FE54D947');
        $this->addSql('ALTER TABLE beer_entry DROP CONSTRAINT FK_D34BC742D0989053');
        $this->addSql('ALTER TABLE "group" DROP CONSTRAINT FK_6DC044C5B03A8386');
        $this->addSql('ALTER TABLE group_member DROP CONSTRAINT FK_A36222A8A76ED395');
        $this->addSql('ALTER TABLE group_member DROP CONSTRAINT FK_A36222A8FE54D947');
        $this->addSql('DROP TABLE beer');
        $this->addSql('DROP TABLE beer_entry');
        $this->addSql('DROP TABLE "group"');
        $this->addSql('DROP TABLE group_member');
        $this->addSql('DROP TABLE "user"');
    }
}
