<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Enum;

/**
 * Catégorie d'un média de la médiathèque. Formats et poids autorisés : réglages de l'administrateur (EditorSettings).
 */
enum MediaType: string
{
    case IMAGE = 'image';
    case DOCUMENT = 'document';

    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Image',
            self::DOCUMENT => 'Document',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::IMAGE => 'Images',
            self::DOCUMENT => 'Documents',
        };
    }
}
