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
 * Réglages de l'éditeur modifiables par l'administrateur (page « Réglages de l'éditeur ») : sources des vidéos (lien,
 * fichier), hôtes des lecteurs vidéo, formats, poids maximal et dossiers de stockage de chaque catégorie de médias
 * (images, documents, vidéos), composants désactivés. Un réglage jamais enregistré prend la valeur de ckeditor_dsfr.defaults.
 *
 * Lus en une requête, une fois par requête HTTP ; base indisponible : valeurs par défaut sans vidéo (la page s'affiche).
 */
final class EditorSettings implements ResetInterface
{
    public const string VIDEO_HOSTS = 'video_hosts';
    public const string VIDEO_SOURCES = 'video_sources';
    public const string DISABLED_COMPONENTS = 'disabled_components';

    /** Vidéo par lien vers une plateforme autorisée (lecteur intégré). */
    public const string VIDEO_LINK = 'link';
    /** Vidéo envoyée dans la médiathèque (lecteur du navigateur). */
    public const string VIDEO_UPLOAD = 'upload';
    public const array VIDEO_SOURCE_CHOICES = [self::VIDEO_LINK, self::VIDEO_UPLOAD];

    private const string HOST_PATTERN = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    /** @var array<string, ?string>|null réglages enregistrés */
    private ?array $stored = null;
    private bool $unavailable = false;

    /**
     * @param array<string, mixed>        $defaults ckeditor_dsfr.defaults (video_sources, video_hosts, disabled_components et, par catégorie : <type>_extensions, <type>_max_size, <type>_directory)
     * @param array<string, list<string>> $catalog  extensions que l'administrateur peut autoriser, par catégorie
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
     * @return list<string> sources de vidéo proposées au rédacteur (VIDEO_LINK, VIDEO_UPLOAD), dans cet ordre
     */
    public function videoSources(): array
    {
        $stored = $this->stored(self::VIDEO_SOURCES);
        $sources = null === $stored ? $this->defaults['video_sources'] : explode(',', $stored);

        return array_values(array_intersect(self::VIDEO_SOURCE_CHOICES, $sources));
    }

    /**
     * Sources réellement utilisables : un lien n'est proposé qu'avec au moins une plateforme autorisée.
     *
     * @return list<string>
     */
    public function offeredVideoSources(): array
    {
        return array_values(array_filter($this->videoSources(), fn (string $source): bool => self::VIDEO_LINK !== $source || [] !== $this->videoHosts()));
    }

    /**
     * @return list<string> extensions autorisées (toujours comprises dans le catalogue)
     */
    public function extensions(MediaType $type): array
    {
        $stored = $this->stored(self::key($type, 'extensions'));
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
        $stored = $this->stored(self::key($type, 'max_size'));
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
        $stored = $this->stored(self::key($type, 'directory'));

        return MediaDirectories::normalize($stored) ?? (string) MediaDirectories::normalize($this->defaults[$type->value.'_directory']);
    }

    /**
     * Enregistre les réglages (valeurs déjà validées par SettingsFormType ; dossiers déjà déplacés par MediaRelocator).
     *
     * @param array<string, mixed> $values video_sources, video_hosts, disabled_components (listes) et, par catégorie : <type>_extensions (liste), <type>_max_size (entier), <type>_directory
     */
    public function save(array $values): void
    {
        $settings = [
            self::VIDEO_SOURCES => implode(',', $values['video_sources']),
            self::VIDEO_HOSTS => implode("\n", $values['video_hosts']),
            self::DISABLED_COMPONENTS => implode(',', $values['disabled_components']),
        ];
        foreach (MediaType::cases() as $type) {
            $settings[self::key($type, 'directory')] = $values[$type->value.'_directory'];
            $settings[self::key($type, 'extensions')] = implode(',', $values[$type->value.'_extensions']);
            $settings[self::key($type, 'max_size')] = (string) $values[$type->value.'_max_size'];
        }
        $this->repository->saveAll($settings);
        $this->reset();
    }

    /** Nom enregistré d'un réglage de catégorie (ex. media_image_extensions, media_video_directory). */
    public static function key(MediaType $type, string $setting): string
    {
        return 'media_'.$type->value.'_'.$setting;
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
                $names = [self::VIDEO_SOURCES, self::VIDEO_HOSTS, self::DISABLED_COMPONENTS];
                foreach (MediaType::cases() as $type) {
                    array_push($names, self::key($type, 'extensions'), self::key($type, 'max_size'), self::key($type, 'directory'));
                }
                $this->stored = $this->repository->values($names);
            } catch (\Throwable $e) {
                $this->logger->error('Réglages de l\'éditeur illisibles : valeurs par défaut, vidéos désactivées.', ['exception' => $e]);
                $this->stored = [];
                $this->unavailable = true;
            }
        }

        return $this->stored[$name] ?? null;
    }
}
