<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Media;

use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Yassrzg\CkeditorDsfrBundle\Repository\MediaRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Changement du dossier de stockage d'une catégorie (réglages de l'éditeur) : les fichiers existants sont déplacés
 * dans le nouveau dossier. Tout ou rien : au premier échec, les fichiers déjà déplacés reviennent à leur place et le
 * réglage n'est pas modifié.
 */
final readonly class MediaRelocator
{
    private Filesystem $filesystem;

    public function __construct(
        private MediaStorage $storage,
        private MediaRepository $repository,
        private LoggerInterface $logger,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * @return int nombre de fichiers déplacés
     *
     * @throws MediaException si le dossier n'est pas utilisable ou si un fichier n'a pas pu être déplacé
     */
    public function relocate(MediaType $type, string $from, string $to): int
    {
        if ($from === $to) {
            return 0;
        }
        $source = $this->storage->directory($type, $from);
        $target = $this->storage->directory($type, $to);

        try {
            $this->filesystem->mkdir($target);
        } catch (IOExceptionInterface $e) {
            $this->logger->error('Dossier de la médiathèque impossible à créer.', ['directory' => $target, 'exception' => $e]);
            throw new MediaException(\sprintf('Le dossier « %s » n\'a pas pu être créé.', $to), 0, $e);
        }
        if (!is_writable($target)) {
            throw new MediaException(\sprintf('Le dossier « %s » n\'est pas accessible en écriture.', $to));
        }

        $moved = [];
        try {
            foreach ($this->repository->filenames($type) as $filename) {
                $file = $source.'/'.MediaStorage::assertSafeFilename($filename);
                if (!is_file($file)) {
                    $this->logger->warning('Fichier de la médiathèque absent, non déplacé.', ['path' => $file]);
                    continue;
                }
                $this->filesystem->rename($file, $target.'/'.$filename);
                $moved[] = $filename;
            }
        } catch (\Throwable $e) {
            foreach ($moved as $filename) {
                try {
                    $this->filesystem->rename($target.'/'.$filename, $source.'/'.$filename);
                } catch (\Throwable $rollback) {
                    $this->logger->critical('Fichier de la médiathèque non remis à sa place.', ['filename' => $filename, 'exception' => $rollback]);
                }
            }
            $this->logger->error('Déplacement des fichiers de la médiathèque interrompu.', ['from' => $source, 'to' => $target, 'exception' => $e]);
            throw new MediaException(\sprintf('Les fichiers n\'ont pas pu être déplacés vers « %s » : le dossier n\'a pas été modifié.', $to), 0, $e);
        }

        $this->logger->info('Fichiers de la médiathèque déplacés.', ['type' => $type->value, 'from' => $source, 'to' => $target, 'count' => \count($moved)]);

        return \count($moved);
    }
}
