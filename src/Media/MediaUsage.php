<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Media;

/**
 * Contenu qui utilise un média (affiché sur la fiche du média ; un média utilisé ne peut pas être supprimé).
 */
final readonly class MediaUsage
{
    public function __construct(
        public string $label,
        public ?string $url = null,
    ) {
    }
}
