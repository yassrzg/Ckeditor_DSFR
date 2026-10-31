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
    case VIDEO = 'video';

    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Image',
            self::DOCUMENT => 'Document',
            self::VIDEO => 'Vidéo',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::IMAGE => 'Images',
            self::DOCUMENT => 'Documents',
            self::VIDEO => 'Vidéos',
        };
    }

    /** Avec article indéfini : « une image », « un document », « une vidéo ». */
    public function withArticle(): string
    {
        return match ($this) {
            self::IMAGE => 'une image',
            self::DOCUMENT => 'un document',
            self::VIDEO => 'une vidéo',
        };
    }

    /** Avec article défini : « l’image », « le document », « la vidéo ». */
    public function withDefiniteArticle(): string
    {
        return match ($this) {
            self::IMAGE => 'l’image',
            self::DOCUMENT => 'le document',
            self::VIDEO => 'la vidéo',
        };
    }

    /** Icône DSFR de la catégorie. */
    public function icon(): string
    {
        return match ($this) {
            self::IMAGE => 'fr-icon-image-line',
            self::DOCUMENT => 'fr-icon-file-text-line',
            self::VIDEO => 'fr-icon-film-line',
        };
    }
}
