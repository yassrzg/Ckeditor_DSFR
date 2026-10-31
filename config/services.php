<?php

declare(strict_types=1);

/*
 * Services du bundle, déclarés explicitement (bonne pratique des bundles réutilisables : ni découverte de fichiers ni
 * autowiring). Identifiants préfixés « ckeditor_dsfr. » ; les classes publiques ont un alias pour l'autowiring du projet.
 */

use Yassrzg\CkeditorDsfrBundle\Command\InstallCommand;
use Yassrzg\CkeditorDsfrBundle\Controller\MediaAdminController;
use Yassrzg\CkeditorDsfrBundle\Controller\MediaController;
use Yassrzg\CkeditorDsfrBundle\Controller\SettingsController;
use Yassrzg\CkeditorDsfrBundle\Doctrine\TableNamesListener;
use Yassrzg\CkeditorDsfrBundle\Editor\ComponentProviderInterface;
use Yassrzg\CkeditorDsfrBundle\Editor\ComponentRegistry;
use Yassrzg\CkeditorDsfrBundle\Editor\ContentRenderer;
use Yassrzg\CkeditorDsfrBundle\Editor\ContentSanitizer;
use Yassrzg\CkeditorDsfrBundle\Editor\ContentText;
use Yassrzg\CkeditorDsfrBundle\Editor\ContentValidator;
use Yassrzg\CkeditorDsfrBundle\Editor\DsfrArtwork;
use Yassrzg\CkeditorDsfrBundle\EventListener\VideoFrameSourceListener;
use Yassrzg\CkeditorDsfrBundle\Form\EditorType;
use Yassrzg\CkeditorDsfrBundle\Form\MediaFormType;
use Yassrzg\CkeditorDsfrBundle\Form\SettingsFormType;
use Yassrzg\CkeditorDsfrBundle\Media\DoctrineMediaUsageProvider;
use Yassrzg\CkeditorDsfrBundle\Media\MediaManager;
use Yassrzg\CkeditorDsfrBundle\Media\MediaRelocator;
use Yassrzg\CkeditorDsfrBundle\Media\MediaStorage;
use Yassrzg\CkeditorDsfrBundle\Media\MediaUsageProviderInterface;
use Yassrzg\CkeditorDsfrBundle\Repository\MediaRepository;
use Yassrzg\CkeditorDsfrBundle\Repository\SettingRepository;
use Yassrzg\CkeditorDsfrBundle\Settings\EditorSettings;
use Yassrzg\CkeditorDsfrBundle\Twig\EditorExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Données (dépôts identifiés par leur classe, comme l'attend Doctrine ; alias ckeditor_dsfr.*)
    $services->set(MediaRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');
    $services->alias('ckeditor_dsfr.media_repository', MediaRepository::class);
    $services->set(SettingRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');
    $services->alias('ckeditor_dsfr.setting_repository', SettingRepository::class);
    $services->set('ckeditor_dsfr.table_names_listener', TableNamesListener::class)
        ->args([param('ckeditor_dsfr.tables')])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    // Réglages de l'administrateur
    $services->set('ckeditor_dsfr.settings', EditorSettings::class)
        ->args([
            service('ckeditor_dsfr.setting_repository'),
            service('logger'),
            param('ckeditor_dsfr.defaults'),
            param('ckeditor_dsfr.extension_catalog'),
            param('ckeditor_dsfr.max_size_limit'),
        ])
        ->tag('kernel.reset', ['method' => 'reset'])
        ->tag('monolog.logger', ['channel' => 'ckeditor_dsfr']);

    // Médiathèque
    $services->set('ckeditor_dsfr.media_storage', MediaStorage::class)
        ->args([param('ckeditor_dsfr.storage_root'), service('ckeditor_dsfr.settings'), service('slugger'), service('logger')])
        ->tag('monolog.logger', ['channel' => 'ckeditor_dsfr']);
    $services->set('ckeditor_dsfr.media_manager', MediaManager::class)
        ->args([
            service('ckeditor_dsfr.media_storage'),
            service('doctrine.orm.entity_manager'),
            service('ckeditor_dsfr.settings'),
            tagged_iterator(MediaUsageProviderInterface::TAG),
        ]);
    $services->set('ckeditor_dsfr.media_relocator', MediaRelocator::class)
        ->args([service('ckeditor_dsfr.media_storage'), service('ckeditor_dsfr.media_repository'), service('logger')])
        ->tag('monolog.logger', ['channel' => 'ckeditor_dsfr']);
    $services->set('ckeditor_dsfr.doctrine_media_usage_provider', DoctrineMediaUsageProvider::class)
        ->args([service('doctrine'), service('router'), param('ckeditor_dsfr.media_usages')])
        ->tag(MediaUsageProviderInterface::TAG);

    // Éditeur
    $services->set('ckeditor_dsfr.component_registry', ComponentRegistry::class)
        ->args([service('ckeditor_dsfr.settings'), param('ckeditor_dsfr.components'), tagged_iterator(ComponentProviderInterface::TAG)])
        ->tag('kernel.reset', ['method' => 'reset']);
    $services->set('ckeditor_dsfr.content_sanitizer', ContentSanitizer::class)
        ->args([service('ckeditor_dsfr.component_registry')]);
    $services->set('ckeditor_dsfr.dsfr_artwork', DsfrArtwork::class)
        ->args([param('ckeditor_dsfr.dsfr_directory'), service('cache.app'), service('assets.packages'), param('ckeditor_dsfr.dsfr_asset_prefix')]);
    $services->set('ckeditor_dsfr.content_renderer', ContentRenderer::class)
        ->args([
            service('ckeditor_dsfr.component_registry'),
            service('ckeditor_dsfr.content_sanitizer'),
            service('ckeditor_dsfr.media_repository'),
            service('twig'),
            service('logger'),
            service('slugger'),
            service('ckeditor_dsfr.dsfr_artwork'),
        ])
        ->tag('monolog.logger', ['channel' => 'ckeditor_dsfr']);
    $services->set('ckeditor_dsfr.content_validator', ContentValidator::class)
        ->args([service('ckeditor_dsfr.component_registry'), service('ckeditor_dsfr.media_repository'), service('ckeditor_dsfr.dsfr_artwork')]);
    $services->set('ckeditor_dsfr.content_text', ContentText::class)
        ->args([service('ckeditor_dsfr.component_registry')]);

    // Formulaires, Twig, en-têtes, console
    $services->set('ckeditor_dsfr.form.editor', EditorType::class)
        ->args([service('ckeditor_dsfr.content_sanitizer'), service('ckeditor_dsfr.content_validator')])
        ->tag('form.type');
    $services->set('ckeditor_dsfr.form.media', MediaFormType::class)
        ->args([service('ckeditor_dsfr.settings')])
        ->tag('form.type');
    $services->set('ckeditor_dsfr.form.settings', SettingsFormType::class)
        ->args([service('ckeditor_dsfr.settings'), service('ckeditor_dsfr.component_registry')])
        ->tag('form.type');
    $services->set('ckeditor_dsfr.twig_extension', EditorExtension::class)
        ->args([
            service('ckeditor_dsfr.content_renderer'),
            service('ckeditor_dsfr.component_registry'),
            service('ckeditor_dsfr.dsfr_artwork'),
            service('assets.packages'),
            service('security.authorization_checker'),
            param('ckeditor_dsfr.layout'),
            param('ckeditor_dsfr.roles'),
        ])
        ->tag('twig.attribute_extension')
        ->tag('twig.runtime');
    $services->set('ckeditor_dsfr.video_frame_source_listener', VideoFrameSourceListener::class)
        ->args([service('ckeditor_dsfr.settings'), param('ckeditor_dsfr.content_security_policy')])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse', 'priority' => -100]);
    $services->set('ckeditor_dsfr.install_command', InstallCommand::class)
        ->args([param('kernel.project_dir'), param('ckeditor_dsfr.dsfr_directory')])
        ->tag('console.command', ['command' => 'ckeditor-dsfr:install']);

    // Contrôleurs
    $controllers = [
        MediaAdminController::class => [service('ckeditor_dsfr.media_manager'), service('ckeditor_dsfr.media_repository'), service('logger'), service('ckeditor_dsfr.dsfr_artwork')],
        MediaController::class => [service('ckeditor_dsfr.media_storage')],
        SettingsController::class => [service('ckeditor_dsfr.settings'), service('ckeditor_dsfr.component_registry'), service('ckeditor_dsfr.media_relocator'), service('logger')],
    ];
    foreach ($controllers as $class => $arguments) {
        $services->set($class)
            ->args($arguments)
            ->public()
            ->call('setContainer')
            ->call('setRoles', [param('ckeditor_dsfr.roles')])
            ->tag('controller.service_arguments')
            ->tag('container.service_subscriber');
    }

    // Alias pour l'autowiring dans le projet
    foreach ([
        EditorSettings::class => 'ckeditor_dsfr.settings',
        MediaStorage::class => 'ckeditor_dsfr.media_storage',
        MediaManager::class => 'ckeditor_dsfr.media_manager',
        MediaRelocator::class => 'ckeditor_dsfr.media_relocator',
        ComponentRegistry::class => 'ckeditor_dsfr.component_registry',
        ContentSanitizer::class => 'ckeditor_dsfr.content_sanitizer',
        ContentValidator::class => 'ckeditor_dsfr.content_validator',
        ContentRenderer::class => 'ckeditor_dsfr.content_renderer',
        ContentText::class => 'ckeditor_dsfr.content_text',
        DsfrArtwork::class => 'ckeditor_dsfr.dsfr_artwork',
    ] as $class => $id) {
        $services->alias($class, $id);
    }
};
