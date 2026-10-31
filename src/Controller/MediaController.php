<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Controller;

use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Yassrzg\CkeditorDsfrBundle\Media\MediaStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Fichiers de la médiathèque, servis aux utilisateurs autorisés (ckeditor_dsfr.roles.view) : images, vidéos et
 * sous-titres (.vtt) lus dans la page, autres documents téléchargés. Routes : config/routes/media.php.
 * Les vidéos sont servies par morceaux (en-tête Range) : le lecteur avance sans tout télécharger.
 */
final class MediaController extends AbstractEditorController
{
    public function __construct(private readonly MediaStorage $storage)
    {
    }

    public function show(Request $request, Media $media): BinaryFileResponse
    {
        $inline = $media->isImage() || MediaType::VIDEO === $media->getType() || 'VTT' === $media->getFormat();

        return $this->serve($request, $media, $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    public function download(Request $request, Media $media): BinaryFileResponse
    {
        return $this->serve($request, $media, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    private function serve(Request $request, Media $media, string $disposition): BinaryFileResponse
    {
        $this->denyUnless('view');
        try {
            $path = $this->storage->path($media);
        } catch (\InvalidArgumentException $e) {
            throw $this->createNotFoundException('Fichier introuvable.', $e);
        }
        if (!is_file($path)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($path, autoLastModified: true);
        if ('VTT' === $media->getFormat()) {
            // Piste de sous-titres : le lecteur du navigateur exige ce type.
            $response->headers->set('Content-Type', 'text/vtt; charset=UTF-8');
        }
        // Cache du navigateur seulement (contenu éventuellement réservé), revalidé au bout d'une heure.
        $response->setPrivate();
        $response->setMaxAge(3600);
        $response->isNotModified($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $name = $media->getTitle().'.'.strtolower($media->getFormat());
        $response->setContentDisposition($disposition, str_replace(['/', '\\'], '_', $name), (string) preg_replace('/[^\x20-\x7E]|[\/\\\\%"]/', '_', $name) ?: 'fichier');

        return $response;
    }
}
