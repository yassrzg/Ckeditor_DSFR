<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Editor;


/**
 * Composants propres à un projet, ajoutés à ceux du bundle (service enregistré automatiquement).
 * Chaque composant a son gabarit templates/bundles/CkeditorDsfrBundle/components/<nom>.html.twig (variable c :
 * values, media, medias, slots, id ; voir ContentRenderer::component()).
 */
interface ComponentProviderInterface
{
    public const string TAG = 'ckeditor_dsfr.component_provider';

    /**
     * @return list<ComponentDefinition>
     */
    public function components(): array;
}
