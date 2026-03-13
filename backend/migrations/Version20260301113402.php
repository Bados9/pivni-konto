<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20260301113402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create admin user';
    }

    public function up(Schema $schema): void
    {
        $id = Uuid::v7()->toRfc4122();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->addSql(
            'INSERT INTO "user" (id, name, email, roles, password, created_at) VALUES (:id, :name, :email, :roles, :password, :created_at) ON CONFLICT (email) DO UPDATE SET roles = :roles',
            [
                'id' => $id,
                'name' => 'Admin',
                'email' => 'admin@pivnikonto.cz',
                'roles' => '["ROLE_ADMIN"]',
                'password' => '$2y$13$nn6ZLcnU4fl.xbdAiGWP2OVuYlqZZb8VGLuJTTKbgzohKeuiouAkm',
                'created_at' => $now,
            ],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM "user" WHERE email = \'admin@pivnikonto.cz\'');
    }
}
