<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Media;

/**
 * Extensions de fichiers de la médiathèque. L'administrateur choisit les extensions autorisées dans un catalogue
 * fixé par la configuration (ckeditor_dsfr.extension_catalog) ; les formats exécutables ou interprétés par le
 * navigateur (scripts, HTML, SVG…) sont toujours refusés, même s'ils sont ajoutés au catalogue.
 * L'extension contrôlée est celle déduite du contenu du fichier, pas de son nom.
 */
final class MediaExtensions
{
    public const array IMAGE_CATALOG = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];

    public const array DOCUMENT_CATALOG = [
        'pdf', 'odt', 'ods', 'odp', 'odg', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'rtf',
        'zip', '7z', 'gz', 'tar', 'json', 'epub', 'mp3', 'mp4', 'webm',
    ];

    /** Formats jamais acceptés (exécution sur le serveur ou dans le navigateur). */
    public const array FORBIDDEN = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp',
        'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'xml', 'xsl', 'js', 'mjs', 'css', 'swf',
        'exe', 'msi', 'bat', 'cmd', 'com', 'sh', 'bash', 'ps1', 'vbs', 'jar', 'hta', 'scr', 'dll', 'so', 'htaccess',
    ];

    private function __construct()
    {
    }

    /**
     * Extensions en minuscules, sans point ni doublon.
     *
     * @param iterable<mixed> $extensions
     *
     * @return list<string>
     */
    public static function normalize(iterable $extensions): array
    {
        $normalized = [];
        foreach ($extensions as $extension) {
            $extension = strtolower(ltrim(trim((string) $extension), '.'));
            if ('' !== $extension && preg_match('/^[a-z0-9]{1,10}$/', $extension)) {
                $normalized[] = $extension;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param iterable<mixed> $extensions
     *
     * @return list<string> extensions de la liste qui sont toujours refusées
     */
    public static function forbidden(iterable $extensions): array
    {
        return array_values(array_intersect(self::normalize($extensions), self::FORBIDDEN));
    }
}
