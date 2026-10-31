<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Settings;

use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Yassrzg\CkeditorDsfrBundle\Media\MediaDirectories;
use Yassrzg\CkeditorDsfrBundle\Media\MediaExtensions;
use Yassrzg\CkeditorDsfrBundle\Repository\SettingRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Réglages de l'éditeur modifiables par l'administrateur (page « Réglages de l'éditeur ») : hôtes des lecteurs vidéo,
 * formats, poids maximal et dossiers de stockage des images et des documents, composants désactivés. Un réglage jamais enregistré prend la
 * valeur de ckeditor_dsfr.defaults.
 *
 * Lus en une requête, une fois par requête HTTP ; base indisponible : valeurs par défaut sans vidéo (la page s'affiche).
 */
final class EditorSettings implements ResetInterface
{
    public const string VIDEO_HOSTS = 'video_hosts';
    public const string IMAGE_EXTENSIONS = 'media_image_extensions';
    public const string DOCUMENT_EXTENSIONS = 'media_document_extensions';
    public const string IMAGE_MAX_SIZE = 'media_image_max_size';
    public const string DOCUMENT_MAX_SIZE = 'media_document_max_size';
    public const string DISABLED_COMPONENTS = 'disabled_components';
    public const string IMAGE_DIRECTORY = 'media_image_directory';
    public const string DOCUMENT_DIRECTORY = 'media_document_directory';

    private const string HOST_PATTERN = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    /** @var array<string, ?string>|null réglages enregistrés */
    private ?array $stored = null;
    private bool $unavailable = false;

    /**
     * @param array{video_hosts: list<string>, image_extensions: list<string>, document_extensions: list<string>, image_max_size: int, document_max_size: int, disabled_components: list<string>, image_directory: string, document_directory: string} $defaults
     * @param array{image: list<string>, document: list<string>} $catalog
     */
    public function __construct(
        private readonly SettingRepository $repository,
        private readonly LoggerInterface $logger,
        private readonly array $defaults,
        private readonly array $catalog,
        private readonly int $maxSizeLimit,
    ) {
    }

    /**
     * @return list<string> hôtes des lecteurs vidéo autorisés (aucun : composant « Vidéo » désactivé)
     */
    public function videoHosts(): array
    {
        $stored = $this->stored(self::VIDEO_HOSTS);
        if ($this->unavailable) {
            return [];
        }

        return null === $stored ? self::parseHosts(implode("\n", $this->defaults['video_hosts']))['hosts'] : self::parseHosts($stored)['hosts'];
    }

    /**
     * @return list<string> extensions autorisées (toujours comprises dans le catalogue)
     */
    public function extensions(MediaType $type): array
    {
        $stored = $this->stored(MediaType::IMAGE === $type ? self::IMAGE_EXTENSIONS : self::DOCUMENT_EXTENSIONS);
        $extensions = MediaExtensions::normalize(null === $stored ? $this->defaults[$type->value.'_extensions'] : explode(',', $stored));

        return array_values(array_intersect($extensions, $this->catalog($type)));
    }

    /**
     * @return list<string> extensions que l'administrateur peut autoriser
     */
    public function catalog(MediaType $type): array
    {
        return array_values(array_diff($this->catalog[$type->value], MediaExtensions::FORBIDDEN));
    }

    /** Poids maximal d'un fichier, en Mo. */
    public function maxSize(MediaType $type): int
    {
        $stored = $this->stored(MediaType::IMAGE === $type ? self::IMAGE_MAX_SIZE : self::DOCUMENT_MAX_SIZE);
        $size = null !== $stored && ctype_digit($stored) ? (int) $stored : $this->defaults[$type->value.'_max_size'];

        return max(1, min($size, $this->maxSizeLimit));
    }

    public function maxSizeLimit(): int
    {
        return $this->maxSizeLimit;
    }

    /**
     * @return list<string> composants retirés de l'éditeur (un contenu existant reste affiché)
     */
    public function disabledComponents(): array
    {
        $stored = $this->stored(self::DISABLED_COMPONENTS);

        return array_values(array_filter(array_map('trim', null === $stored ? $this->defaults['disabled_components'] : explode(',', $stored))));
    }

    /** Sous-dossier de ckeditor_dsfr.storage_root où sont stockés les fichiers de cette catégorie. */
    public function directory(MediaType $type): string
    {
        $stored = $this->stored(MediaType::IMAGE === $type ? self::IMAGE_DIRECTORY : self::DOCUMENT_DIRECTORY);

        return MediaDirectories::normalize($stored) ?? (string) MediaDirectories::normalize($this->defaults[$type->value.'_directory']);
    }

    /**
     * Enregistre les réglages (valeurs déjà validées par SettingsFormType ; dossiers déjà déplacés par MediaRelocator).
     *
     * @param array{video_hosts: list<string>, image_extensions: list<string>, document_extensions: list<string>, image_max_size: int, document_max_size: int, disabled_components: list<string>, image_directory: string, document_directory: string} $values
     */
    public function save(array $values): void
    {
        $this->repository->saveAll([
            self::IMAGE_DIRECTORY => $values['image_directory'],
            self::DOCUMENT_DIRECTORY => $values['document_directory'],
            self::VIDEO_HOSTS => implode("\n", $values['video_hosts']),
            self::IMAGE_EXTENSIONS => implode(',', $values['image_extensions']),
            self::DOCUMENT_EXTENSIONS => implode(',', $values['document_extensions']),
            self::IMAGE_MAX_SIZE => (string) $values['image_max_size'],
            self::DOCUMENT_MAX_SIZE => (string) $values['document_max_size'],
            self::DISABLED_COMPONENTS => implode(',', $values['disabled_components']),
        ]);
        $this->reset();
    }

    /**
     * Saisie des hôtes (un par ligne ou séparés par des virgules ; une adresse complète est réduite à son hôte).
     *
     * @return array{hosts: list<string>, invalid: list<string>}
     */
    public static function parseHosts(string $input): array
    {
        $hosts = $invalid = [];
        foreach (preg_split('/[\s,;]+/', strtolower($input), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
            $host = str_contains($entry, '/') ? (string) parse_url(str_contains($entry, '://') ? $entry : 'https://'.$entry, \PHP_URL_HOST) : $entry;
            if (preg_match(self::HOST_PATTERN, $host)) {
                $hosts[] = $host;
            } else {
                $invalid[] = $entry;
            }
        }

        return ['hosts' => array_values(array_unique($hosts)), 'invalid' => $invalid];
    }

    public function reset(): void
    {
        $this->stored = null;
        $this->unavailable = false;
    }

    private function stored(string $name): ?string
    {
        if (null === $this->stored) {
            try {
                $this->stored = $this->repository->values([
                    self::VIDEO_HOSTS, self::IMAGE_EXTENSIONS, self::DOCUMENT_EXTENSIONS, self::IMAGE_MAX_SIZE, self::DOCUMENT_MAX_SIZE,
                    self::DISABLED_COMPONENTS, self::IMAGE_DIRECTORY, self::DOCUMENT_DIRECTORY,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Réglages de l\'éditeur illisibles : valeurs par défaut, vidéos désactivées.', ['exception' => $e]);
                $this->stored = [];
                $this->unavailable = true;
            }
        }

        return $this->stored[$name] ?? null;
    }
}
