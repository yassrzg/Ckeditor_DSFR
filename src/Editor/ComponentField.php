<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Editor;

use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;

/**
 * Réglage d'un composant de l'éditeur, enregistré dans l'attribut data-<name> du composant.
 * Types : text, textarea, url, choice (valeurs de options), media (identifiant d'un média de mediaType, limité
 * éventuellement à certains formats : formats), medias (identifiants de plusieurs médias de mediaType, dans l'ordre
 * d'affichage : « 12,4,7 »),
 * icon (classe d'icône du DSFR : « fr-icon-information-line »), pictogram (pictogramme du DSFR : « digital/search »).
 *
 * Conditions d'affichage (fenêtre de réglages) : un réglage, ou une valeur d'une liste, n'est proposé que si un autre
 * réglage le permet (ex. orientation des documents seulement en cartes ou tuiles, position de l'icône seulement avec une
 * icône). Condition : ['field' => nom, 'values' => [...]] (valeur parmi la liste), ['field' => nom ou [noms],
 * 'filled' => true] (au moins un renseigné) ou ['field' => nom, 'filled' => false] (vide). Un réglage masqué n'est pas enregistré.
 *
 * Réglages par média (items, champ medias) : chaque média choisi peut avoir ses propres valeurs (ex. pictogramme et
 * apparence d'un document en tuile), enregistrées en JSON dans data-<name>-settings : {"12": {"pictogram": "…"}}.
 */
final readonly class ComponentField
{
    /**
     * @param array<string, string>              $options     valeur => libellé (type choice)
     * @param array<string, mixed>|null          $showIf      condition d'affichage du réglage
     * @param array<string, array<string, mixed>> $optionShowIf valeur => condition d'affichage de cette valeur
     * @param list<ComponentField>               $items        réglages propres à chaque média (type medias)
     * @param array<string, mixed>|null          $itemsShowIf  condition d'affichage des réglages par média
     * @param list<string>                       $formats      formats acceptés pour un média (ex. ['vtt']), vide : tous
     */
    private function __construct(
        public string $name,
        public string $label,
        public string $type,
        public bool $required = false,
        public array $options = [],
        public ?string $default = null,
        public ?MediaType $mediaType = null,
        public ?string $hint = null,
        public int $maxLength = 500,
        public ?array $showIf = null,
        public array $optionShowIf = [],
        public array $items = [],
        public ?array $itemsShowIf = null,
        public array $formats = [],
    ) {
    }

    /**
     * Réglage déclaré dans le catalogue YAML (ComponentCatalog, tableau déjà contrôlé).
     *
     * @param array<string, mixed> $f
     */
    public static function fromCatalog(array $f, string $videoHosts = ''): self
    {
        return new self(
            $f['name'], $f['label'], $f['type'], $f['required'], $f['options'], $f['default'],
            null === $f['mediaType'] ? null : MediaType::from($f['mediaType']),
            null === $f['hint'] ? null : str_replace('{video_hosts}', $videoHosts, (string) $f['hint']),
            $f['maxLength'], $f['showIf'], $f['optionShowIf'],
            array_values(array_map(static fn (array $item): self => self::fromCatalog($item), $f['items'])),
            $f['itemsShowIf'],
            $f['formats'] ?? [],
        );
    }

    /**
     * Réglage proposé seulement si le réglage $field vaut l'une des $values.
     *
     * @param list<string> $values
     */
    public function when(string $field, array $values): self
    {
        return $this->with(showIf: ['field' => $field, 'values' => $values]);
    }

    /**
     * Réglage proposé seulement si l'un des réglages $fields est renseigné (true) ou si $fields est vide (false).
     *
     * @param string|list<string> $fields
     */
    public function whenFilled(string|array $fields, bool $filled = true): self
    {
        return $this->with(showIf: ['field' => $fields, 'filled' => $filled]);
    }

    /**
     * Valeur $option de la liste proposée seulement si la condition est remplie (même forme que showIf).
     *
     * @param array<string, mixed> $condition
     */
    public function optionWhen(string $option, array $condition): self
    {
        return $this->with(optionShowIf: [$option => $condition] + $this->optionShowIf);
    }

    /**
     * @param array<string, mixed>|null           $showIf
     * @param array<string, array<string, mixed>>|null $optionShowIf
     */
    private function with(?array $showIf = null, ?array $optionShowIf = null): self
    {
        return new self($this->name, $this->label, $this->type, $this->required, $this->options, $this->default, $this->mediaType,
            $this->hint, $this->maxLength, $showIf ?? $this->showIf, $optionShowIf ?? $this->optionShowIf, $this->items, $this->itemsShowIf, $this->formats);
    }

    public static function text(string $name, string $label, bool $required = false, ?string $hint = null, int $maxLength = 500): self
    {
        return new self($name, $label, 'text', $required, hint: $hint, maxLength: $maxLength);
    }

    public static function textarea(string $name, string $label, bool $required = false, ?string $hint = null, int $maxLength = 10000): self
    {
        return new self($name, $label, 'textarea', $required, hint: $hint, maxLength: $maxLength);
    }

    public static function url(string $name, string $label, bool $required = true, ?string $hint = null): self
    {
        return new self($name, $label, 'url', $required, hint: $hint, maxLength: 2000);
    }

    /**
     * @param array<string, string> $options
     */
    public static function choice(string $name, string $label, array $options, ?string $default = null, ?string $hint = null): self
    {
        return new self($name, $label, 'choice', true, $options, $default ?? (string) array_key_first($options), hint: $hint);
    }

    public static function media(string $name, string $label, MediaType $type, bool $required = true, ?string $hint = null): self
    {
        return new self($name, $label, 'media', $required, mediaType: $type, hint: $hint, maxLength: 20);
    }

    /** Nombre maximal de médias d'un champ medias. */
    public const int MAX_MEDIAS = 50;

    public static function medias(string $name, string $label, MediaType $type, ?string $hint = null): self
    {
        return new self($name, $label, 'medias', true, mediaType: $type, hint: $hint, maxLength: 20 * self::MAX_MEDIAS);
    }

    public static function icon(string $name, string $label, ?string $hint = null): self
    {
        return new self($name, $label, 'icon', false, hint: $hint, maxLength: 80);
    }

    public static function pictogram(string $name, string $label, ?string $hint = null): self
    {
        return new self($name, $label, 'pictogram', false, hint: $hint, maxLength: 80);
    }

    /**
     * Réglage proposé pour ces valeurs (même règle que la fenêtre de l'éditeur : un réglage masqué compte comme vide).
     * Un réglage obligatoire masqué n'est pas exigé.
     *
     * @param array<string, string> $values    valeurs brutes du composant, par nom de réglage
     * @param array<string, self>   $fields    réglages du composant, par nom
     */
    public function isShown(array $values, array $fields, int $depth = 0): bool
    {
        if (null === $this->showIf || $depth > 5) {
            return true;
        }
        $value = static function (string $name) use ($values, $fields, $depth): string {
            $field = $fields[$name] ?? null;

            return null !== $field && $field->isShown($values, $fields, $depth + 1) ? trim($values[$name] ?? '') : '';
        };
        if (isset($this->showIf['values'])) {
            return \in_array($value((string) $this->showIf['field']), $this->showIf['values'], true);
        }
        $filled = [] !== array_filter(array_map($value, (array) $this->showIf['field']), static fn (string $v): bool => '' !== $v);

        return $this->showIf['filled'] ? $filled : !$filled;
    }

    /** Média accepté par ce réglage : bonne catégorie et, si le réglage les limite, bon format. */
    public function accepts(Media $media): bool
    {
        return $media->getType() === $this->mediaType && ([] === $this->formats || \in_array(strtolower($media->getFormat()), $this->formats, true));
    }

    /** Attribut des réglages par média (JSON), pour un champ medias avec items. */
    public function settingsAttribute(): string
    {
        return $this->attribute().'-settings';
    }

    /** Nom de l'attribut HTML enregistré. */
    public function attribute(): string
    {
        return 'data-'.$this->name;
    }

    /**
     * @return array<string, mixed> description transmise à l'éditeur (JavaScript)
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'attribute' => $this->attribute(),
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
            'options' => $this->options ?: null,
            'default' => $this->default,
            'mediaType' => $this->mediaType?->value,
            'formats' => $this->formats ?: null,
            'hint' => $this->hint,
            'maxLength' => $this->maxLength,
            'showIf' => self::conditionForEditor($this->showIf),
            'optionShowIf' => [] === $this->optionShowIf ? null : array_map(self::conditionForEditor(...), $this->optionShowIf),
            'items' => [] === $this->items ? null : array_map(static fn (self $item): array => $item->toArray(), $this->items),
            'itemsShowIf' => self::conditionForEditor($this->itemsShowIf),
            'settingsAttribute' => [] === $this->items ? null : $this->settingsAttribute(),
        ], static fn (mixed $v): bool => null !== $v);
    }

    /**
     * Condition pour l'éditeur : noms de réglages remplacés par leurs attributs (data-…).
     *
     * @param array<string, mixed>|null $condition
     *
     * @return array<string, mixed>|null
     */
    private static function conditionForEditor(?array $condition): ?array
    {
        if (null === $condition) {
            return null;
        }
        $condition['field'] = array_map(static fn (string $f): string => 'data-'.$f, (array) $condition['field']);

        return $condition;
    }
}
