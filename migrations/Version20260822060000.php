<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260822060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add confirmation token expiration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD verification_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP verification_token_expires_at');
    }
}