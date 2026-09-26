<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926093100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track sent web push on notifications - group award pushes are deferred to 10:00';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification ADD pushed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        // historic awards were pushed immediately at creation time
        $this->addSql("UPDATE notification SET pushed_at = created_at WHERE type = 'group_award'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP pushed_at');
    }
}
