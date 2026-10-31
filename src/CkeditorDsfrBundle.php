<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle;

use Yassrzg\CkeditorDsfrBundle\Doctrine\BundleSchema;
use Yassrzg\CkeditorDsfrBundle\Editor\ComponentCatalog;
use Yassrzg\CkeditorDsfrBundle\Editor\ComponentProviderInterface;
use Yassrzg\CkeditorDsfrBundle\Media\MediaDirectories;
use Yassrzg\CkeditorDsfrBundle\Media\MediaExtensions;
use Yassrzg\CkeditorDsfrBundle\Media\MediaUsageProviderInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Éditeur CKEditor 5 à composants DSFR, médiathèque et réglages administrables (configuration : clé ckeditor_dsfr).
 * Documentation : docs/installation.md et docs/configuration.md du bundle.
 */
final class CkeditorDsfrBundle extends AbstractBundle
{
    /** Chemin logique AssetMapper des fichiers JavaScript et CSS du bundle (dossier assets/). */
    public const string ASSET_NAMESPACE = '@yassrzg/ckeditor-dsfr-bundle';

    protected string $extensionAlias = 'ckeditor_dsfr';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('layout')
                    ->info('Gabarit étendu par les pages d\'administration du bundle (voir templates/admin/_layout.html.twig).')
                    ->defaultValue('base.html.twig')
                ->end()
                ->arrayNode('dsfr')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('directory')
                            ->info('Dossier des fichiers du DSFR (icônes et pictogrammes proposés dans l\'éditeur).')
                            ->defaultValue('%kernel.project_dir%/assets/dsfr')
                        ->end()
                        ->scalarNode('asset_prefix')
                            ->info('Chemin logique (AssetMapper ou public/) de ce même dossier, pour les adresses des pictogrammes.')
                            ->defaultValue('dsfr')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('component_files')
                    ->info('Fichiers YAML de composants du projet (même format que config/components.yaml du bundle), lus après celui du bundle : un composant du même nom le remplace, « enabled: false » le retire.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('storage_root')
                    ->info('Dossier racine des fichiers de la médiathèque : l\'administrateur choisit les sous-dossiers des images et des documents.')
                    ->defaultValue('%kernel.project_dir%/var/ckeditor_dsfr')
                ->end()
                ->arrayNode('tables')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('media')->defaultValue('ckeditor_dsfr_media')->cannotBeEmpty()->end()
                        ->scalarNode('setting')->defaultValue('ckeditor_dsfr_setting')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->booleanNode('migrations')
                    ->info('Déclare la migration du bundle (création des tables manquantes). false : le projet crée lui-même les tables.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('roles')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('admin')->info('Médiathèque et éditeur.')->defaultValue('ROLE_ADMIN')->end()
                        ->scalarNode('settings')->info('Page des réglages.')->defaultValue('ROLE_ADMIN')->end()
                        ->scalarNode('view')->info('Consultation des fichiers (PUBLIC_ACCESS : tout le monde).')->defaultValue('PUBLIC_ACCESS')->end()
                    ->end()
                ->end()
                ->arrayNode('extension_catalog')
                    ->info('Extensions que l\'administrateur peut autoriser, par catégorie (les formats exécutables sont toujours refusés).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('image')->scalarPrototype()->end()->defaultValue(MediaExtensions::IMAGE_CATALOG)->end()
                        ->arrayNode('document')->scalarPrototype()->end()->defaultValue(MediaExtensions::DOCUMENT_CATALOG)->end()
                    ->end()
                ->end()
                ->integerNode('max_size_limit')
                    ->info('Poids maximal (Mo) que l\'administrateur peut fixer pour un fichier.')
                    ->defaultValue(200)->min(1)
                ->end()
                ->arrayNode('defaults')
                    ->info('Réglages appliqués tant que l\'administrateur ne les a pas modifiés.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('video_hosts')->scalarPrototype()->end()->defaultValue([])->end()
                        ->arrayNode('image_extensions')->scalarPrototype()->end()->defaultValue(['jpg', 'jpeg', 'png', 'webp'])->end()
                        ->arrayNode('document_extensions')->scalarPrototype()->end()
                            ->defaultValue(['pdf', 'odt', 'ods', 'odp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'zip'])
                        ->end()
                        ->integerNode('image_max_size')->defaultValue(10)->min(1)->end()
                        ->integerNode('document_max_size')->defaultValue(50)->min(1)->end()
                        ->arrayNode('disabled_components')->scalarPrototype()->end()->defaultValue([])->end()
                        ->scalarNode('image_directory')->info('Sous-dossier de storage_root.')->defaultValue('image')->end()
                        ->scalarNode('document_directory')->info('Sous-dossier de storage_root.')->defaultValue('document')->end()
                    ->end()
                ->end()
                ->arrayNode('media_usages')
                    ->info('Contenus qui utilisent les médias : un média utilisé ne peut pas être supprimé.')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('class')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('label')->info('Propriété affichée (ex. title).')->isRequired()->end()
                            ->arrayNode('content_fields')->info('Champs saisis avec l\'éditeur.')->scalarPrototype()->end()->defaultValue([])->end()
                            ->arrayNode('relation_fields')->info('Relations vers un média (ex. vignette).')->scalarPrototype()->end()->defaultValue([])->end()
                            ->scalarNode('route')->info('Route de modification du contenu.')->defaultNull()->end()
                            ->arrayNode('route_parameters')
                                ->info('Paramètres de la route : nom => propriété du contenu.')
                                ->useAttributeAsKey('name')->scalarPrototype()->end()
                                ->defaultValue(['id' => 'id'])
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
                ->booleanNode('content_security_policy')
                    ->info('Ajoute les hôtes vidéo autorisés à la directive frame-src d\'un en-tête Content-Security-Policy existant.')
                    ->defaultFalse()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $c): bool => null === MediaDirectories::normalize($c['defaults']['image_directory']) || null === MediaDirectories::normalize($c['defaults']['document_directory']))
                ->thenInvalid('ckeditor_dsfr.defaults : sous-dossier non valide (lettres, chiffres, - et _, séparés par /).')
            ->end()
            ->validate()
                ->ifTrue(static fn (array $c): bool => [] !== MediaExtensions::forbidden([...$c['extension_catalog']['image'], ...$c['extension_catalog']['document']]))
                ->then(static function (array $c): never {
                    throw new \InvalidArgumentException('ckeditor_dsfr.extension_catalog : formats refusés : '.implode(', ', MediaExtensions::forbidden([...$c['extension_catalog']['image'], ...$c['extension_catalog']['document']])).'.');
                })
            ->end();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $parameters = $container->parameters();
        $parameters->set('ckeditor_dsfr.layout', $config['layout']);
        $parameters->set('ckeditor_dsfr.dsfr_directory', $config['dsfr']['directory']);
        $parameters->set('ckeditor_dsfr.dsfr_asset_prefix', trim($config['dsfr']['asset_prefix'], '/'));
        $parameters->set('ckeditor_dsfr.storage_root', $config['storage_root']);
        $parameters->set('ckeditor_dsfr.tables', $config['tables']);
        $parameters->set('ckeditor_dsfr.roles', $config['roles']);
        $parameters->set('ckeditor_dsfr.extension_catalog', array_map(MediaExtensions::normalize(...), $config['extension_catalog']));
        $parameters->set('ckeditor_dsfr.max_size_limit', $config['max_size_limit']);
        $parameters->set('ckeditor_dsfr.defaults', $config['defaults']);
        $parameters->set('ckeditor_dsfr.media_usages', $config['media_usages']);
        $parameters->set('ckeditor_dsfr.content_security_policy', $config['content_security_policy']);

        // Catalogue des composants : lu et contrôlé ici, une seule fois ; une modification d'un fichier recompile le conteneur.
        $files = [ComponentCatalog::FILE];
        foreach ($config['component_files'] as $file) {
            $files[] = (string) $builder->getParameterBag()->resolveValue($file);
        }
        foreach ($files as $file) {
            $builder->addResource(new FileResource($file));
        }
        // « % » doublé : un libellé ou une aide n'est jamais lu comme un paramètre du conteneur.
        $parameters->set('ckeditor_dsfr.components', self::escapeParameters(ComponentCatalog::load($files)));

        $container->import('../config/services.php');

        // Extensions du projet : services enregistrés automatiquement s'ils implémentent ces interfaces.
        $builder->registerForAutoconfiguration(ComponentProviderInterface::class)->addTag(ComponentProviderInterface::TAG);
        $builder->registerForAutoconfiguration(MediaUsageProviderInterface::class)->addTag(MediaUsageProviderInterface::TAG);
    }

    private static function escapeParameters(mixed $value): mixed
    {
        return match (true) {
            \is_string($value) => str_replace('%', '%%', $value),
            \is_array($value) => array_map(self::escapeParameters(...), $value),
            default => $value,
        };
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $config = $builder->getExtensionConfig('ckeditor_dsfr');
        $migrations = true;
        foreach ($config as $part) {
            $migrations = $part['migrations'] ?? $migrations;
        }

        $builder->prependExtensionConfig('doctrine', ['orm' => ['mappings' => ['CkeditorDsfrBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => $this->getPath().'/src/Entity',
            'prefix' => 'Yassrzg\CkeditorDsfrBundle\Entity',
            'alias' => 'CkeditorDsfr',
        ]]]]);
        if ($migrations && $builder->hasExtension('doctrine_migrations')) {
            $builder->prependExtensionConfig('doctrine_migrations', ['migrations_paths' => [
                'Yassrzg\CkeditorDsfrBundle\Migrations' => $this->getPath().'/src/Migrations',
            ]]);
        }
        $builder->prependExtensionConfig('framework', ['asset_mapper' => ['paths' => [$this->getPath().'/assets' => self::ASSET_NAMESPACE]]]);
        $builder->prependExtensionConfig('twig', ['form_themes' => ['@CkeditorDsfr/form/editor.html.twig']]);
    }

    public function boot(): void
    {
        // Migration du bundle : tables créées d'après le mapping Doctrine du projet (noms de tables, stratégie d'identifiants).
        BundleSchema::setRegistry(fn (): ?object => $this->container?->get('doctrine'));
    }
}
