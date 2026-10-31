<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Media;

use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Yassrzg\CkeditorDsfrBundle\Settings\EditorSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Ajout, modification et suppression des médias de la médiathèque.
 * Un média utilisé par un contenu (MediaUsageProviderInterface) ne peut pas être supprimé.
 */
final readonly class MediaManager
{
    /**
     * @param iterable<MediaUsageProviderInterface> $usageProviders
     */
    public function __construct(
        private MediaStorage $storage,
        private EntityManagerInterface $entityManager,
        private EditorSettings $settings,
        private iterable $usageProviders,
    ) {
    }

    /**
     * Enregistre le média ; un nouveau fichier remplace l'ancien (même identifiant : les contenus suivent).
     * Le fichier est écrit avant la base : en cas d'échec, rien n'est modifié.
     *
     * @throws MediaException si le format n'est pas accepté ou si un nouveau média n'a pas de fichier
     */
    public function save(Media $media, ?UploadedFile $file): void
    {
        if (null === $file && null === $media->getId()) {
            throw new MediaException('Choisissez un fichier.');
        }

        $previous = $media->getFilename();
        if (null !== $file) {
            if (!$this->storage->isAllowed($file, $media->getType())) {
                throw new MediaException(\sprintf('Format non accepté. Formats acceptés : %s.', strtoupper(implode(', ', $this->settings->extensions($media->getType())))));
            }
            $this->storage->store($file, $media);
        }

        try {
            $this->entityManager->persist($media->touch());
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            if (null !== $file) {
                $this->storage->remove($media->getType(), $media->getFilename());
            }
            throw $e;
        }

        if (null !== $file && '' !== $previous && $previous !== $media->getFilename()) {
            $this->storage->remove($media->getType(), $previous);
        }
    }

    /**
     * @return list<MediaUsage>
     */
    public function usages(Media $media): array
    {
        if (null === $media->getId()) {
            return [];
        }

        $usages = [];
        foreach ($this->usageProviders as $provider) {
            $usages = [...$usages, ...$provider->usages($media)];
        }

        return $usages;
    }

    /**
     * @throws MediaException si le média est utilisé
     */
    public function delete(Media $media): void
    {
        $usages = $this->usages($media);
        if ([] !== $usages) {
            throw new MediaException(\sprintf(
                'Ce média est utilisé par : %s. Retirez-le de ces contenus avant de le supprimer.',
                implode(', ', array_map(static fn (MediaUsage $u): string => '« '.$u->label.' »', $usages)),
            ));
        }

        $type = $media->getType();
        $filename = $media->getFilename();
        $this->entityManager->remove($media);
        $this->entityManager->flush();
        $this->storage->remove($type, $filename);
    }
}
