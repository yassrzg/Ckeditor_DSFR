<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Media;

use Yassrzg\CkeditorDsfrBundle\Entity\Media;

/**
 * Contenus d'un projet qui utilisent un média. Les contenus courants (champ saisi avec l'éditeur, relation vers un
 * média) se déclarent sans code dans ckeditor_dsfr.media_usages ; un cas particulier implémente cette interface
 * (service enregistré automatiquement).
 */
interface MediaUsageProviderInterface
{
    public const string TAG = 'ckeditor_dsfr.media_usage_provider';

    /**
     * @return list<MediaUsage>
     */
    public function usages(Media $media): array;
}
