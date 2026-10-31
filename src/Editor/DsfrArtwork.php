<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Editor;

use Symfony\Component\Asset\Packages;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Icônes (classes fr-icon-*) et pictogrammes (illustrations SVG) du DSFR proposés dans l'éditeur à composants.
 * Liste lue dans les fichiers du DSFR (ckeditor_dsfr.dsfr.directory) et mise en cache : aucun fichier déposé par un utilisateur.
 * Sert aussi à valider les valeurs enregistrées (une icône inconnue n'est pas affichée).
 */
final readonly class DsfrArtwork
{
    public function __construct(
        private string $dsfrDirectory,
        private CacheInterface $cache,
        private Packages $packages,
        private string $assetPrefix = 'dsfr',
    ) {
    }

    /**
     * @return array<string, string> classe fr-icon-* => catégorie (arrows, system…), par ordre alphabétique
     */
    public function icons(): array
    {
        return $this->cache->get('ckeditor_dsfr_icons', function (): array {
            $css = (string) file_get_contents($this->dsfrDirectory.'/utility/icons/icons.min.css');
            preg_match_all('/\.(fr-icon-[a-z0-9-]+):{1,2}before/', $css, $matches);
            $classes = array_flip($matches[1]);
            $icons = [];
            foreach (glob($this->dsfrDirectory.'/icons/*/*.svg') ?: [] as $file) {
                // Icônes propres au DSFR : fichier « fr--warning-line.svg » pour la classe fr-icon-warning-line.
                $class = 'fr-icon-'.preg_replace('/^fr--/', '', basename($file, '.svg'));
                if (isset($classes[$class])) {
                    $icons[$class] = basename(\dirname($file));
                }
            }
            ksort($icons);

            return $icons;
        });
    }

    /**
     * @return list<string> pictogrammes « catégorie/nom » (ex. digital/search), par ordre alphabétique
     */
    public function pictograms(): array
    {
        return $this->cache->get('ckeditor_dsfr_pictograms', function (): array {
            $pictograms = array_map(
                fn (string $file): string => basename(\dirname($file)).'/'.basename($file, '.svg'),
                glob($this->dsfrDirectory.'/artwork/pictograms/*/*.svg') ?: [],
            );
            sort($pictograms);

            return $pictograms;
        });
    }

    public function isIcon(string $value): bool
    {
        return isset($this->icons()[$value]);
    }

    public function isPictogram(string $value): bool
    {
        return \in_array($value, $this->pictograms(), true);
    }

    public function pictogramUrl(string $pictogram): string
    {
        return $this->packages->getUrl($this->assetPrefix.'/artwork/pictograms/'.$pictogram.'.svg');
    }
}
