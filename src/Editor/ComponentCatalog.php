<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Editor;

use Symfony\Component\Yaml\Yaml;
use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;

/**
 * Catalogue des composants déclaré en YAML (config/components.yaml du bundle, puis fichiers du projet :
 * ckeditor_dsfr.component_files). Lu et contrôlé à la compilation du conteneur (load) : une erreur de déclaration
 * empêche le démarrage avec un message qui nomme le fichier, le composant et le réglage. Le résultat (tableaux simples)
 * est un paramètre du conteneur, transformé en ComponentDefinition par le ComponentRegistry (definition).
 */
final class ComponentCatalog
{
    public const string FILE = __DIR__.'/../../config/components.yaml';

    private const array FIELD_TYPES = ['text', 'textarea', 'url', 'choice', 'media', 'medias', 'icon', 'pictogram'];
    private const array ITEM_TYPES = ['text', 'choice', 'icon', 'pictogram'];
    private const array COMPONENT_KEYS = ['label', 'description', 'icon', 'category', 'group', 'layout', 'legacy', 'width', 'fields', 'slots', 'enabled'];
    private const array FIELD_KEYS = ['type', 'label', 'hint', 'required', 'max_length', 'default', 'options', 'media_type', 'formats', 'when', 'when_filled', 'when_empty', 'option_when', 'items', 'items_when'];
    private const array SLOT_KEYS = ['label', 'inline', 'required', 'components', 'tables'];
    /** Obligatoire par défaut selon le type (lien, liste de choix, média). */
    private const array REQUIRED_BY_DEFAULT = ['url', 'choice', 'media', 'medias'];
    private const array MAX_LENGTHS = ['textarea' => 10000, 'url' => 2000, 'media' => 20, 'medias' => 20 * ComponentField::MAX_MEDIAS, 'icon' => 80, 'pictogram' => 80];

    /**
     * @param list<string> $files fichiers lus dans l'ordre : un composant du même nom remplace le précédent
     *
     * @return array<string, array<string, mixed>> composants normalisés, par nom
     */
    public static function load(array $files): array
    {
        $lists = [];
        $components = [];
        foreach ($files as $file) {
            $data = Yaml::parseFile($file);
            if (!\is_array($data)) {
                throw self::error($file, 'le fichier doit contenir « lists » et « components ».');
            }
            foreach ($data['lists'] ?? [] as $name => $options) {
                $lists[(string) $name] = self::options($file, 'lists.'.$name, $options);
            }
            foreach ($data['components'] ?? [] as $name => $component) {
                $name = (string) $name;
                if (!preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
                    throw self::error($file, \sprintf('nom de composant « %s » : lettres minuscules, chiffres et tirets.', $name));
                }
                if (!\is_array($component)) {
                    throw self::error($file, \sprintf('composant « %s » : description attendue.', $name));
                }
                unset($components[$name]);
                if (false !== ($component['enabled'] ?? true)) {
                    $components[$name] = [$file, $component];
                }
            }
        }

        $catalog = [];
        foreach ($components as $name => [$file, $component]) {
            $catalog[$name] = self::component($file, $name, $component, $lists);
        }

        return $catalog;
    }

    /**
     * @param array<string, mixed> $c composant normalisé
     */
    public static function definition(string $name, array $c, string $videoHosts): ComponentDefinition
    {
        return new ComponentDefinition(
            $name, $c['label'], $c['description'], $c['icon'],
            array_values(array_map(static fn (array $f): ComponentField => ComponentField::fromCatalog($f, $videoHosts), $c['fields'])),
            $c['slots'], $c['group'], $c['layout'], $c['legacy'], $c['width'], $c['category'] ?? null,
        );
    }

    /**
     * @param array<mixed>                         $c
     * @param array<string, array<string, string>> $lists
     *
     * @return array<string, mixed>
     */
    private static function component(string $file, string $name, array $c, array $lists): array
    {
        $where = 'composant « '.$name.' »';
        self::keys($file, $where, $c, self::COMPONENT_KEYS);
        foreach (['label', 'description', 'icon'] as $key) {
            if (!\is_string($c[$key] ?? null) || '' === $c[$key]) {
                throw self::error($file, $where.' : « '.$key.' » obligatoire.');
            }
        }

        $fields = [];
        foreach ((array) ($c['fields'] ?? []) as $fieldName => $field) {
            $fields[(string) $fieldName] = self::field($file, $where, (string) $fieldName, $field, $lists, self::FIELD_TYPES);
        }
        foreach ($fields as $field) {
            self::checkConditions($file, $where, $field, $fields);
        }

        $slots = [];
        foreach ((array) ($c['slots'] ?? []) as $slotName => $slot) {
            $slot = (array) $slot;
            self::keys($file, $where.', zone « '.$slotName.' »', $slot, self::SLOT_KEYS);
            $slots[] = array_filter([
                'name' => (string) $slotName,
                'label' => (string) ($slot['label'] ?? 'Contenu'),
                'inline' => (bool) ($slot['inline'] ?? false),
                'required' => (bool) ($slot['required'] ?? false),
                'components' => ($slot['components'] ?? false) ?: null,
                'tables' => ($slot['tables'] ?? false) ?: null,
            ], static fn (mixed $v): bool => null !== $v);
        }

        $layout = (bool) ($c['layout'] ?? false);
        if ($layout && (!isset($fields['columns']) || 'choice' !== $fields['columns']['type'] || 1 !== \count($slots))) {
            throw self::error($file, $where.' : un bloc de colonnes (layout) a un réglage « columns » (liste de dispositions « 4-8 ») et une seule zone.');
        }

        $width = null;
        if (isset($c['width'])) {
            $w = (array) $c['width'];
            self::keys($file, $where.', width', $w, ['min', 'grouped', 'per_media', 'when']);
            if (!\is_int($w['min'] ?? null) || $w['min'] < 1 || $w['min'] > 12) {
                throw self::error($file, $where.' : width.min, nombre de colonnes de 1 à 12.');
            }
            $width = array_filter([
                'min' => $w['min'],
                'grouped' => ($w['grouped'] ?? false) ?: null,
                'perMedia' => $w['per_media'] ?? null,
                'when' => isset($w['when']) ? self::condition($file, $where.', width', $w['when']) : null,
            ], static fn (mixed $v): bool => null !== $v);
        }

        return [
            'label' => $c['label'],
            'description' => $c['description'],
            'icon' => $c['icon'],
            'category' => isset($c['category']) ? (string) $c['category'] : null,
            'group' => isset($c['group']) ? (string) $c['group'] : null,
            'layout' => $layout,
            'legacy' => (bool) ($c['legacy'] ?? false),
            'width' => $width,
            'fields' => array_values($fields),
            'slots' => $slots,
        ];
    }

    /**
     * @param array<string, array<string, string>> $lists
     * @param list<string>                         $types
     *
     * @return array<string, mixed>
     */
    private static function field(string $file, string $where, string $name, mixed $f, array $lists, array $types): array
    {
        $where .= ', réglage « '.$name.' »';
        if (!\is_array($f) || !preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
            throw self::error($file, $where.' : nom en minuscules et description attendus.');
        }
        self::keys($file, $where, $f, self::FIELD_KEYS);
        $type = $f['type'] ?? null;
        if (!\in_array($type, $types, true)) {
            throw self::error($file, $where.' : type parmi '.implode(', ', $types).'.');
        }
        if (!\is_string($f['label'] ?? null)) {
            throw self::error($file, $where.' : « label » obligatoire.');
        }

        $options = [];
        if ('choice' === $type) {
            $raw = $f['options'] ?? null;
            if (\is_string($raw) && str_starts_with($raw, '@')) {
                $options = $lists[substr($raw, 1)] ?? throw self::error($file, $where.' : liste « '.$raw.' » inconnue.');
            } else {
                $options = self::options($file, $where, $raw);
            }
        }
        $mediaType = null;
        if (\in_array($type, ['media', 'medias'], true)) {
            $mediaType = MediaType::tryFrom((string) ($f['media_type'] ?? '')) ?? throw self::error($file, $where.' : media_type parmi '.implode(', ', array_column(MediaType::cases(), 'value')).'.');
        }
        $formats = array_values(array_map(static fn (mixed $format): string => strtolower((string) $format), (array) ($f['formats'] ?? [])));
        if ([] !== $formats && 'media' !== $type) {
            throw self::error($file, $where.' : « formats » seulement pour un réglage media.');
        }
        $required = (bool) ($f['required'] ?? \in_array($type, self::REQUIRED_BY_DEFAULT, true));
        $default = isset($f['default']) ? (string) $f['default'] : ('choice' === $type && $required ? (string) array_key_first($options) : null);
        if (null !== $default && 'choice' === $type && !\array_key_exists($default, $options)) {
            throw self::error($file, $where.' : valeur par défaut absente de la liste.');
        }

        $items = [];
        if (isset($f['items'])) {
            if ('medias' !== $type) {
                throw self::error($file, $where.' : « items » seulement pour un réglage medias.');
            }
            foreach ((array) $f['items'] as $itemName => $item) {
                $items[] = self::field($file, $where, (string) $itemName, $item, $lists, self::ITEM_TYPES);
            }
        }

        $optionShowIf = [];
        foreach ((array) ($f['option_when'] ?? []) as $option => $condition) {
            if (!\array_key_exists((string) $option, $options)) {
                throw self::error($file, $where.' : option_when sur une valeur absente de la liste (« '.$option.' »).');
            }
            $optionShowIf[(string) $option] = self::condition($file, $where, $condition);
        }

        return [
            'name' => $name,
            'type' => $type,
            'label' => $f['label'],
            'required' => $required,
            'options' => $options,
            'default' => $default,
            'mediaType' => $mediaType?->value,
            'hint' => isset($f['hint']) ? (string) $f['hint'] : null,
            'maxLength' => (int) ($f['max_length'] ?? self::MAX_LENGTHS[$type] ?? 500),
            'showIf' => self::showIf($file, $where, $f),
            'optionShowIf' => $optionShowIf,
            'items' => $items,
            'itemsShowIf' => isset($f['items_when']) ? self::condition($file, $where, $f['items_when']) : null,
            'formats' => $formats,
        ];
    }

    /**
     * @param array<mixed> $f
     *
     * @return array<string, mixed>|null
     */
    private static function showIf(string $file, string $where, array $f): ?array
    {
        $conditions = array_filter([
            isset($f['when']) ? self::condition($file, $where, $f['when']) : null,
            isset($f['when_filled']) ? ['field' => array_map('strval', (array) $f['when_filled']), 'filled' => true] : null,
            isset($f['when_empty']) ? ['field' => (string) $f['when_empty'], 'filled' => false] : null,
        ]);
        if (\count($conditions) > 1) {
            throw self::error($file, $where.' : une seule condition parmi when, when_filled, when_empty.');
        }

        return array_values($conditions)[0] ?? null;
    }

    /**
     * @return array{field: string, values: list<string>}
     */
    private static function condition(string $file, string $where, mixed $condition): array
    {
        if (!\is_array($condition) || 1 !== \count($condition) || !\is_array(reset($condition))) {
            throw self::error($file, $where.' : condition attendue sous la forme {réglage: [valeurs]}.');
        }

        return ['field' => (string) array_key_first($condition), 'values' => array_values(array_map('strval', reset($condition)))];
    }

    /**
     * Les conditions portent sur des réglages du même composant.
     *
     * @param array<string, mixed>                $field
     * @param array<string, array<string, mixed>> $fields
     */
    private static function checkConditions(string $file, string $where, array $field, array $fields): void
    {
        $conditions = [$field['showIf'], $field['itemsShowIf'], ...array_values($field['optionShowIf'])];
        foreach (array_filter($conditions) as $condition) {
            foreach ((array) $condition['field'] as $other) {
                if (!isset($fields[$other])) {
                    throw self::error($file, $where.', réglage « '.$field['name'].' » : condition sur un réglage inconnu (« '.$other.' »).');
                }
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private static function options(string $file, string $where, mixed $options): array
    {
        if (!\is_array($options) || [] === $options) {
            throw self::error($file, $where.' : liste de valeurs (valeur: libellé) attendue.');
        }
        $normalized = [];
        foreach ($options as $value => $label) {
            // YAML : « yes », « no », « true »… s'écrivent entre guillemets (sinon lus comme des booléens).
            $normalized[(string) $value] = (string) $label;
        }

        return $normalized;
    }

    /**
     * @param array<mixed> $data
     * @param list<string> $allowed
     */
    private static function keys(string $file, string $where, array $data, array $allowed): void
    {
        $unknown = array_diff(array_map('strval', array_keys($data)), $allowed);
        if ([] !== $unknown) {
            throw self::error($file, $where.' : clé inconnue « '.implode('», «', $unknown).' » (attendu : '.implode(', ', $allowed).').');
        }
    }

    private static function error(string $file, string $message): \InvalidArgumentException
    {
        return new \InvalidArgumentException(\sprintf('Catalogue des composants (%s) : %s', $file, $message));
    }
}
