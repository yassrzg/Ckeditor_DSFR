<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Editor;

/**
 * Composant de l'éditeur.
 *
 * - fields : réglages (attributs data-*), saisis dans la fenêtre du composant ;
 * - slots : zones rédigées directement dans l'éditeur (titre sur une ligne, contenu riche), vide pour un composant
 *   « atomique » (image, téléchargement…) ; une zone « components » accepte aussi les composants à zones (colonne d'un
 *   bloc de mise en page, répétée autant de fois que la disposition compte de colonnes) ;
 * - group : des composants consécutifs du même groupe sont affichés ensemble (accordéons, onglets, grille de cartes,
 *   boutons) par @CkeditorDsfr/groups/<group>.html.twig ;
 * - layout : bloc de mise en page (colonnes, disposition dans le réglage « columns », ex. « 4-8 »), jamais placé dans
 *   la zone d'un autre composant ;
 * - legacy : ancien composant, toujours affiché et modifiable mais plus proposé à l'insertion ni dans les réglages ;
 * - width : largeur minimale, en colonnes de la grille de 12, exigée dans une colonne d'un bloc « Colonnes » :
 *   ['min' => 3] ; 'grouped' : par élément d'une ligne de composants consécutifs du même groupe (cartes en grille,
 *   4 par ligne au plus) ; 'perMedia' + 'when' : par document de ce réglage, si la condition est remplie. Un élément
 *   horizontal (réglage orientation « horizontal… ») compte double et une ligne en contient 2 au plus.
 */
final readonly class ComponentDefinition
{
    /**
     * @param list<ComponentField>                                                                      $fields
     * @param list<array{name: string, label: string, inline: bool, required: bool, components?: bool, tables?: bool}> $slots
     * @param array{min: int, grouped?: bool, perMedia?: string, when?: array{field: string, values: list<string>}}|null $width
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $description,
        public string $icon,
        public array $fields = [],
        public array $slots = [],
        public ?string $group = null,
        public bool $layout = false,
        public bool $legacy = false,
        public ?array $width = null,
    ) {
    }

    public function hasSlots(): bool
    {
        return [] !== $this->slots;
    }

    public function field(string $name): ?ComponentField
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'icon' => $this->icon,
            'fields' => array_map(static fn (ComponentField $f): array => $f->toArray(), $this->fields),
            'slots' => $this->slots,
            'group' => $this->group,
            'layout' => $this->layout ?: null,
            'width' => $this->width,
        ], static fn (mixed $v): bool => null !== $v);
    }
}
