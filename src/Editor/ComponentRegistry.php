<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Editor;

use Yassrzg\CkeditorDsfrBundle\Settings\EditorSettings;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Liste unique des composants de l'éditeur, déclarée en YAML (config/components.yaml, fichiers du projet :
 * ComponentCatalog) : elle alimente l'éditeur (JavaScript), le filtre HTML et le contrôle à l'enregistrement
 * (ContentSanitizer, ContentValidator) et le rendu DSFR à l'affichage (ContentRenderer, gabarits @CkeditorDsfr/components/).
 * Composant propre à un projet : fichier YAML (option component_files) ou ComponentProviderInterface, et gabarit
 * templates/bundles/CkeditorDsfrBundle/components/<nom>.html.twig.
 * L'administrateur peut retirer des composants de l'éditeur (EditorSettings) ; « Vidéo » n'est proposé que si une
 * source est utilisable (lien avec au moins un hôte autorisé, ou fichier). Un composant retiré ou ancien (legacy) déjà présent dans un contenu reste affiché et
 * modifiable : l'éditeur reçoit tous les composants, seuls les composants proposés sont « insertable ».
 */
final class ComponentRegistry implements ResetInterface
{
    /** @var array<string, ComponentDefinition>|null tous les composants, désactivés compris */
    private ?array $definitions = null;

    /**
     * @param array<string, array<string, mixed>>  $catalog   composants du catalogue YAML (ComponentCatalog::load)
     * @param iterable<ComponentProviderInterface> $providers composants propres au projet déclarés en PHP
     */
    public function __construct(
        private readonly EditorSettings $settings,
        private readonly array $catalog,
        private readonly iterable $providers = [],
    ) {
    }

    /**
     * @return list<string> hôtes des lecteurs vidéo autorisés (réglage de l'administrateur)
     */
    public function videoHosts(): array
    {
        return $this->settings->videoHosts();
    }

    /**
     * Composant affichable, même retiré de l'éditeur par l'administrateur (une vidéo par lien n'est affichée que si
     * son hôte est autorisé : ContentRenderer).
     */
    public function get(string $name): ?ComponentDefinition
    {
        return $this->definitions()[$name] ?? null;
    }

    /**
     * Composants proposés à l'insertion : ni anciens, ni retirés par l'administrateur, ni vidéo sans source utilisable.
     *
     * @return array<string, ComponentDefinition>
     */
    public function all(): array
    {
        $definitions = array_diff_key($this->selectable(), array_flip($this->settings->disabledComponents()));
        if ([] === $this->settings->offeredVideoSources()) {
            unset($definitions['video']);
        }

        return $definitions;
    }

    /**
     * Tous les composants, même retirés ou désactivés : le filtre HTML conserve leurs réglages (une vidéo déjà insérée
     * n'est pas effacée si les vidéos sont désactivées puis réactivées) et l'éditeur les modifie.
     *
     * @return array<string, ComponentDefinition>
     */
    public function definitions(): array
    {
        if (null === $this->definitions) {
            $this->definitions = [];
            foreach ([...$this->build(), ...$this->custom()] as $definition) {
                $this->definitions[$definition->name] = $definition;
            }
        }

        return $this->definitions;
    }

    /**
     * Composants que l'administrateur propose ou retire (page des réglages) : tous sauf les anciens.
     *
     * @return array<string, ComponentDefinition>
     */
    public function selectable(): array
    {
        return array_filter($this->definitions(), static fn (ComponentDefinition $d): bool => !$d->legacy);
    }

    /**
     * @return list<ComponentDefinition>
     */
    private function custom(): array
    {
        $definitions = [];
        foreach ($this->providers as $provider) {
            $definitions = [...$definitions, ...$provider->components()];
        }

        return $definitions;
    }

    public function reset(): void
    {
        $this->definitions = null;
    }

    /**
     * Attributs data-* acceptés par le filtre HTML (data-component, data-slot et les réglages de tous les composants).
     *
     * @return list<string>
     */
    public function attributes(): array
    {
        $attributes = ['data-component', 'data-slot'];
        foreach ($this->definitions() as $definition) {
            foreach ($definition->fields as $field) {
                $attributes[] = $field->attribute();
                if ([] !== $field->items) {
                    $attributes[] = $field->settingsAttribute();
                }
            }
        }

        return array_values(array_unique($attributes));
    }

    /**
     * Réglages textuels (titre, description, légende, transcription…) : repris par la recherche de documentation.
     *
     * @return list<string>
     */
    public function textAttributes(): array
    {
        $attributes = [];
        foreach ($this->definitions() as $definition) {
            foreach ($definition->fields as $field) {
                if (\in_array($field->type, ['text', 'textarea'], true)) {
                    $attributes[] = $field->attribute();
                }
            }
        }

        return array_values(array_unique($attributes));
    }

    /**
     * Description de tous les composants pour l'éditeur : un composant non proposé (retiré, ancien, vidéo sans source)
     * reste lu et réenregistré tel quel, il n'est simplement plus insérable. Vidéo : le réglage « source » ne propose
     * que les sources autorisées (offered) ; une vidéo existante garde la sienne.
     *
     * @return list<array<string, mixed>>
     */
    public function forEditor(): array
    {
        $insertable = $this->all();
        $sources = $this->settings->offeredVideoSources();

        return array_values(array_map(
            static function (ComponentDefinition $d) use ($insertable, $sources): array {
                $component = [...$d->toArray(), 'insertable' => isset($insertable[$d->name])];
                if ('video' === $d->name) {
                    foreach ($component['fields'] as $i => $field) {
                        if ('source' === $field['name']) {
                            $component['fields'][$i]['offered'] = $sources;
                        }
                    }
                }

                return $component;
            },
            $this->definitions(),
        ));
    }

    /**
     * @return list<ComponentDefinition>
     */
    private function build(): array
    {
        $hosts = implode(', ', $this->videoHosts()) ?: 'aucune';

        return array_map(
            static fn (string $name, array $component): ComponentDefinition => ComponentCatalog::definition($name, $component, $hosts),
            array_keys($this->catalog),
            $this->catalog,
        );
    }
}
