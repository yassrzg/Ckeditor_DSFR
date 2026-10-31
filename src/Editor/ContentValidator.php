<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Editor;

use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Yassrzg\CkeditorDsfrBundle\Repository\MediaRepository;
use Masterminds\HTML5;

/**
 * Contrôle à l'enregistrement d'un contenu de l'éditeur (après ContentSanitizer), pour un envoi qui ne viendrait pas de
 * l'éditeur (requête forgée, HTML modifié à la main) : composants connus, valeurs des listes de choix, adresses,
 * icônes et pictogrammes du DSFR, médias existants et du bon type, réglages par média, emplacements (tous les composants
 * dans une zone de contenu ou une colonne, aucun dans un titre ni dans une cellule ; tableaux dans le composant Tableau
 * seulement) et nombre de colonnes.
 * L'éditeur ne produit jamais ces erreurs ; le rendu (ContentRenderer) revalide de toute façon chaque valeur.
 */
final class ContentValidator
{
    private readonly HTML5 $html5;

    public function __construct(
        private readonly ComponentRegistry $registry,
        private readonly MediaRepository $mediaRepository,
        private readonly DsfrArtwork $artwork,
    ) {
        $this->html5 = new HTML5(['disable_html_ns' => true]);
    }

    /**
     * @return list<string> messages d'erreur, vide si le contenu est valide
     */
    public function validate(?string $html): array
    {
        if (null === $html || (!str_contains($html, 'data-component') && !str_contains($html, '<table'))) {
            return [];
        }

        $errors = [];
        $medias = [];
        $this->walk($this->html5->loadHTMLFragment($html), 'content', false, $errors, $medias);

        $found = $this->mediaRepository->findByIds(array_keys($medias));
        foreach ($medias as $id => [$type, $label]) {
            if (!isset($found[$id])) {
                $errors[] = \sprintf('« %s » : le média n° %d n\'existe pas, retirez-le du composant.', $label, $id);
            } elseif ($found[$id]->getType() !== $type) {
                $errors[] = \sprintf('« %s » : le média n° %d n\'est pas %s.', $label, $id, MediaType::IMAGE === $type ? 'une image' : 'un document');
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param 'content'|'inline'|'cell'                  $context emplacement : contenu (texte, zone, colonne), titre, cellule de tableau
     * @param bool                                       $tables  zone qui accepte un tableau (composant Tableau)
     * @param list<string>                               $errors
     * @param array<int, array{0: MediaType, 1: string}> $medias médias cités (type attendu, composant)
     */
    private function walk(\DOMNode $parent, string $context, bool $tables, array &$errors, array &$medias): void
    {
        foreach ($parent->childNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            if (!$node->hasAttribute('data-component')) {
                if ('table' === $node->nodeName) {
                    if ($tables) {
                        $this->walk($node, 'cell', false, $errors, $medias);
                    } else {
                        $errors[] = 'Un tableau se place dans le composant « Tableau ».';
                    }
                    continue;
                }
                $this->walk($node, $context, $tables, $errors, $medias);
                continue;
            }

            $name = $node->getAttribute('data-component');
            $definition = $this->registry->definitions()[$name] ?? null;
            if (null === $definition) {
                $errors[] = \sprintf('Composant inconnu : « %s ».', $name);
                continue;
            }
            if ('content' !== $context) {
                $errors[] = \sprintf('« %s » ne peut pas être placé dans un titre ni dans une cellule de tableau.', $definition->label);
                continue;
            }
            $this->checkFields($definition, $node, $errors, $medias);
            $this->checkSlots($definition, $node, $errors, $medias);
        }
    }

    /**
     * @param list<string>                               $errors
     * @param array<int, array{0: MediaType, 1: string}> $medias
     */
    private function checkFields(ComponentDefinition $definition, \DOMElement $node, array &$errors, array &$medias): void
    {
        foreach ($definition->fields as $field) {
            $raw = trim($node->getAttribute($field->attribute()));
            // Ancien composant « Téléchargement » à un seul document (data-media-id).
            if ('medias' === $field->type && '' === $raw) {
                $raw = trim($node->getAttribute('data-media-id'));
            }
            if ('' === $raw) {
                continue;
            }
            $valid = match ($field->type) {
                'choice' => \array_key_exists($raw, $field->options),
                'url' => null !== ContentRenderer::safeUrl($raw),
                'icon' => $this->artwork->isIcon($raw),
                'pictogram' => $this->artwork->isPictogram($raw),
                'media' => 1 === preg_match('/^\d+$/', $raw),
                'medias' => 1 === preg_match('/^\d+(,\d+){0,'.(ComponentField::MAX_MEDIAS - 1).'}$/', $raw),
                default => true,
            };
            if (!$valid) {
                $errors[] = \sprintf('« %s » : valeur non autorisée pour « %s ».', $definition->label, $field->label);
                continue;
            }
            if (null !== $field->mediaType && \in_array($field->type, ['media', 'medias'], true)) {
                foreach (explode(',', $raw) as $id) {
                    $medias[(int) $id] = [$field->mediaType, $definition->label];
                }
            }
            if ([] !== $field->items && '' !== $node->getAttribute($field->settingsAttribute())) {
                $this->checkItemSettings($definition, $field, explode(',', $raw), $node->getAttribute($field->settingsAttribute()), $errors);
            }
        }
    }

    /**
     * Réglages par média : objet JSON {identifiant du média : {réglage : valeur}} limité aux médias et réglages du champ.
     *
     * @param list<string> $ids
     * @param list<string> $errors
     */
    private function checkItemSettings(ComponentDefinition $definition, ComponentField $field, array $ids, string $json, array &$errors): void
    {
        $settings = json_decode($json, true);
        $message = \sprintf('« %s » : réglages par média non valides pour « %s ».', $definition->label, $field->label);
        if (!\is_array($settings) || array_is_list($settings) && [] !== $settings) {
            $errors[] = $message;

            return;
        }
        $items = [];
        foreach ($field->items as $item) {
            $items[$item->name] = $item;
        }
        foreach ($settings as $id => $values) {
            if (!\in_array((string) $id, $ids, true) || !\is_array($values)) {
                $errors[] = $message;

                return;
            }
            foreach ($values as $name => $value) {
                $item = $items[$name] ?? null;
                $valid = null !== $item && \is_string($value) && mb_strlen($value) <= $item->maxLength && match ($item->type) {
                    'choice' => \array_key_exists($value, $item->options),
                    'icon' => $this->artwork->isIcon($value),
                    'pictogram' => $this->artwork->isPictogram($value),
                    default => true,
                };
                if (!$valid) {
                    $errors[] = $message;

                    return;
                }
            }
        }
    }

    /**
     * @param list<string>                               $errors
     * @param array<int, array{0: MediaType, 1: string}> $medias
     */
    private function checkSlots(ComponentDefinition $definition, \DOMElement $node, array &$errors, array &$medias): void
    {
        $names = array_column($definition->slots, 'name');
        $columns = 0;
        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$child->hasAttribute('data-slot')) {
                continue;
            }
            if (!\in_array($child->getAttribute('data-slot'), $names, true)) {
                $errors[] = \sprintf('« %s » : zone inconnue « %s ».', $definition->label, $child->getAttribute('data-slot'));
                continue;
            }
            $columns += (int) $definition->layout;
            // Zone d'un titre (sur une ligne) : aucun composant ; zone « tables » : un tableau.
            $slot = array_values(array_filter($definition->slots, static fn (array $s): bool => $s['name'] === $child->getAttribute('data-slot')))[0];
            $this->walk($child, $slot['inline'] ? 'inline' : 'content', $slot['tables'] ?? false, $errors, $medias);
        }

        if ($definition->layout) {
            $layout = $node->getAttribute('data-columns');
            $expected = \count(explode('-', \array_key_exists($layout, $definition->field('columns')->options ?? []) ? $layout : (string) $definition->field('columns')?->default));
            if ($columns !== $expected) {
                $errors[] = \sprintf('Le bloc « %s » doit contenir %d colonnes, il en contient %d.', $definition->label, $expected, $columns);
            }
        }
    }
}
