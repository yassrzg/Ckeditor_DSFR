<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Media;

use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Yassrzg\CkeditorDsfrBundle\Settings\EditorSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Fichiers de la médiathèque : <ckeditor_dsfr.storage_root>/<dossier de la catégorie>/<nom généré>, le dossier de
 * chaque catégorie étant choisi par l'administrateur (EditorSettings::directory(), image, document et video par défaut).
 * Les fichiers sont servis par MediaController (droits de ckeditor_dsfr.roles.view).
 */
final readonly class MediaStorage
{
    private Filesystem $filesystem;

    public function __construct(
        private string $storageRoot,
        private EditorSettings $settings,
        private SluggerInterface $slugger,
        private LoggerInterface $logger,
    ) {
        $this->filesystem = new Filesystem();
    }

    /** Extension réelle (déduite du contenu) autorisée par l'administrateur pour cette catégorie. */
    public function isAllowed(UploadedFile $file, MediaType $type): bool
    {
        return \UPLOAD_ERR_OK === $file->getError() && \in_array(self::extension($file), $this->settings->extensions($type), true);
    }

    /**
     * Extension déduite du contenu du fichier. Sous-titres WebVTT : texte brut pour la détection, reconnus à leur
     * en-tête « WEBVTT » (exigé par le format) et à leur nom .vtt.
     */
    public static function extension(UploadedFile $file): string
    {
        $extension = strtolower((string) $file->guessExtension());
        if ('txt' === $extension && 'vtt' === strtolower($file->getClientOriginalExtension())
            && 1 === preg_match('/^(\xEF\xBB\xBF)?WEBVTT([ \t\r\n]|$)/', (string) @file_get_contents($file->getPathname(), length: 16))) {
            return 'vtt';
        }

        return $extension;
    }

    /**
     * Écrit le fichier envoyé et renseigne le média (nom, format, poids, dimensions d'une image).
     *
     * @throws \RuntimeException si l'écriture échoue
     */
    public function store(UploadedFile $file, Media $media): void
    {
        $name = $this->slugger->slug(pathinfo($file->getClientOriginalName(), \PATHINFO_FILENAME))->lower()->toString() ?: 'fichier';
        $filename = \sprintf('%s-%s.%s', mb_substr($name, 0, 150), uniqid(), self::extension($file));
        $originalName = $file->getClientOriginalName();
        $mimeType = 'vtt' === self::extension($file) ? 'text/vtt' : (string) $file->getMimeType();
        $size = (int) $file->getSize();
        // Dimensions lues sans bibliothèque d'image (getimagesize) : utiles au navigateur pour réserver la place.
        $dimensions = $media->isImage() ? @getimagesize($file->getPathname()) : false;

        try {
            $file->move($this->directory($media->getType()), self::assertSafeFilename($filename));
        } catch (FileException $e) {
            throw new \RuntimeException('Échec de l\'enregistrement du fichier.', 0, $e);
        }

        $media->setFile($filename, $originalName, $mimeType, $size, $dimensions ? $dimensions[0] : null, $dimensions ? $dimensions[1] : null);
    }

    /**
     * @throws \InvalidArgumentException si le nom stocké n'est pas un simple nom de fichier
     */
    public function path(Media $media): string
    {
        return $this->directory($media->getType()).'/'.self::assertSafeFilename($media->getFilename());
    }

    public function remove(MediaType $type, string $filename): void
    {
        if ('' === $filename) {
            return;
        }

        try {
            $path = $this->directory($type).'/'.self::assertSafeFilename($filename);
            if ($this->filesystem->exists($path)) {
                $this->filesystem->remove($path);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Suppression du fichier d\'un média impossible.', ['filename' => $filename, 'exception' => $e]);
        }
    }

    /** Nom de fichier sans chemin (pas de sortie du dossier), sinon exception. */
    public static function assertSafeFilename(string $filename): string
    {
        if ('' === $filename || basename($filename) !== $filename || str_starts_with($filename, '.')) {
            throw new \InvalidArgumentException(\sprintf('Nom de fichier invalide : "%s".', $filename));
        }

        return $filename;
    }

    /** Dossier absolu des fichiers d'une catégorie ($directory : sous-dossier validé, celui des réglages par défaut). */
    public function directory(MediaType $type, ?string $directory = null): string
    {
        return rtrim($this->storageRoot, '/').'/'.($directory ?? $this->settings->directory($type));
    }
}
