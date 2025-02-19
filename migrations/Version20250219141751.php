<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250219141751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE media ADD original_height INT DEFAULT NULL');
        $this->addSql('ALTER TABLE media ADD original_width INT DEFAULT NULL');
        $this->addSql('ALTER TABLE media ADD ext VARCHAR(4) DEFAULT NULL');
        $this->addSql('ALTER TABLE media ADD exif JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE media DROP original_height');
        $this->addSql('ALTER TABLE media DROP original_width');
        $this->addSql('ALTER TABLE media DROP ext');
        $this->addSql('ALTER TABLE media DROP exif');
    }
}
