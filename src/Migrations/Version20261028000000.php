<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Migrations;

use Yassrzg\CkeditorDsfrBundle\Doctrine\BundleSchema;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tables de la médiathèque et des réglages de l'éditeur (noms : ckeditor_dsfr.tables).
 * Additive et idempotente : seules les tables absentes sont créées ; aucune table existante n'est modifiée.
 */
final class Version20261028000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CKEditor DSFR : tables de la médiathèque et des réglages (si absentes)';
    }

    public function up(Schema $schema): void
    {
        $queries = BundleSchema::createSql();
        $this->skipIf([] === $queries, 'Les tables du bundle CkeditorDsfrBundle existent déjà.');

        foreach ($queries as $query) {
            $this->addSql($query);
        }
    }

    public function down(Schema $schema): void
    {
        // Pas de suppression automatique : la médiathèque contient des données.
        $this->throwIrreversibleMigrationException('Supprimez les tables de la médiathèque à la main si besoin.');
    }
}
