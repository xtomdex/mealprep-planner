<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125085702 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalog: products, with a unique name (PRD-02)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE catalog_products (id UUID NOT NULL, name VARCHAR(120) NOT NULL, measurement_category VARCHAR(16) NOT NULL, created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uq_catalog_products_name ON catalog_products (name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE catalog_products');
    }
}
