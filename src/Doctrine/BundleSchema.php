<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Doctrine;

use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Yassrzg\CkeditorDsfrBundle\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Création des tables du bundle par sa migration : le SQL est produit par Doctrine d'après le mapping du projet
 * (noms de tables configurés, plateforme, stratégie d'identifiants), seulement pour les tables absentes.
 * Le registre Doctrine est fourni au démarrage du bundle (CkeditorDsfrBundle::boot()).
 */
final class BundleSchema
{
    /** @var (\Closure(): ?object)|null */
    private static ?\Closure $registry = null;

    private function __construct()
    {
    }

    /**
     * @param \Closure(): ?object $registry
     */
    public static function setRegistry(\Closure $registry): void
    {
        self::$registry = $registry;
    }

    /**
     * @return list<string> requêtes de création des tables manquantes (vide si elles existent toutes)
     */
    public static function createSql(): array
    {
        $registry = null === self::$registry ? null : (self::$registry)();
        if (!$registry instanceof ManagerRegistry) {
            throw new \LogicException('Doctrine n\'est pas disponible : le bundle CkeditorDsfrBundle doit être activé.');
        }
        $manager = $registry->getManagerForClass(Media::class);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException('Aucun gestionnaire Doctrine ORM pour les entités du bundle CkeditorDsfrBundle.');
        }

        $schemaManager = $manager->getConnection()->createSchemaManager();
        $missing = [];
        foreach ([Media::class, Setting::class] as $class) {
            $metadata = $manager->getClassMetadata($class);
            if (!$schemaManager->tablesExist([$metadata->getTableName()])) {
                $missing[] = $metadata;
            }
        }

        return [] === $missing ? [] : (new SchemaTool($manager))->getCreateSchemaSql($missing);
    }
}
