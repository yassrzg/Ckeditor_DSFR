<?php

declare(strict_types=1);

/*
 * Fichiers de la médiathèque (droits : ckeditor_dsfr.roles.view). À importer à la racine du site :
 *   ckeditor_dsfr_media: { resource: '@CkeditorDsfrBundle/config/routes/media.php' }
 */

use Yassrzg\CkeditorDsfrBundle\Controller\MediaController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Requirement\Requirement;

return static function (RoutingConfigurator $routes): void {
    $routes->add('ckeditor_dsfr_media_show', '/media/{id}')->controller([MediaController::class, 'show'])->requirements(['id' => Requirement::DIGITS])->methods(['GET']);
    $routes->add('ckeditor_dsfr_media_download', '/media/{id}/telecharger')->controller([MediaController::class, 'download'])->requirements(['id' => Requirement::DIGITS])->methods(['GET']);
};
