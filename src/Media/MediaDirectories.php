<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Media;

/**
 * Sous-dossiers de stockage choisis par l'administrateur, toujours sous ckeditor_dsfr.storage_root : chemin relatif
 * fait de lettres, chiffres, tirets et soulignés (ex. « contenu/image ») ; ni « .. », ni chemin absolu.
 */
final class MediaDirectories
{
    private const string PATTERN = '~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+){0,4}$~';

    private function __construct()
    {
    }

    /** Sous-dossier sans barre oblique au début ni à la fin, null s'il n'est pas valide. */
    public static function normalize(?string $directory): ?string
    {
        $directory = trim(str_replace('\\', '/', trim((string) $directory)), '/');

        return 1 === preg_match(self::PATTERN, $directory) ? $directory : null;
    }
}
