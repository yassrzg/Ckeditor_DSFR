<?php

declare(strict_types=1);

/*
 * Administration : médiathèque, données de l'éditeur et réglages. À importer sous le préfixe de l'administration :
 *   ckeditor_dsfr_admin: { resource: '@CkeditorDsfrBundle/config/routes/admin.php', prefix: /admin }
 */

use Yassrzg\CkeditorDsfrBundle\Controller\MediaAdminController;
use Yassrzg\CkeditorDsfrBundle\Controller\SettingsController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Requirement\Requirement;

return static function (RoutingConfigurator $routes): void {
    $type = ['type' => 'image|document'];
    $id = ['id' => Requirement::DIGITS];

    $routes->add('ckeditor_dsfr_media_index', '/mediatheque/')->controller([MediaAdminController::class, 'index'])->methods(['GET']);
    $routes->add('ckeditor_dsfr_media_create', '/mediatheque/ajouter/{type}')->controller([MediaAdminController::class, 'create'])->requirements($type)->methods(['GET', 'POST']);
    $routes->add('ckeditor_dsfr_media_edit', '/mediatheque/{id}/modifier')->controller([MediaAdminController::class, 'edit'])->requirements($id)->methods(['GET', 'POST']);
    $routes->add('ckeditor_dsfr_media_delete', '/mediatheque/{id}/supprimer')->controller([MediaAdminController::class, 'delete'])->requirements($id)->methods(['POST']);
    $routes->add('ckeditor_dsfr_media_selection', '/mediatheque/selection/{type}')->controller([MediaAdminController::class, 'selection'])->requirements($type)->methods(['GET']);
    $routes->add('ckeditor_dsfr_artwork', '/mediatheque/dsfr')->controller([MediaAdminController::class, 'artwork'])->methods(['GET']);
    $routes->add('ckeditor_dsfr_media_quick_add', '/mediatheque/ajout-rapide/{type}')->controller([MediaAdminController::class, 'quickAdd'])->requirements($type)->methods(['GET', 'POST']);
    $routes->add('ckeditor_dsfr_settings', '/reglages-editeur')->controller(SettingsController::class)->methods(['GET', 'POST']);
};
