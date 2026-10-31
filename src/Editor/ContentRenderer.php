<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Editor;

use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Yassrzg\CkeditorDsfrBundle\Repository\MediaRepository;
use Masterminds\HTML5;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Twig\Environment;

/**
 * Affichage d'un contenu de l'éditeur : le HTML enregistré est filtré, puis chaque composant (<div data-component>)
 * est rendu en DSFR par son gabarit @CkeditorDsfr/components/<nom>.html.twig. Les composants consécutifs d'un même
 * groupe (accordéons, onglets, cartes, boutons) passent par @CkeditorDsfr/groups/<groupe>.html.twig.
 *
 * Les réglages sont revalidés ici (liste de valeurs, adresses, médias existants) : un composant incomplet ou un média
 * supprimé n'est pas affiché. Le code DSFR n'étant jamais enregistré, une évolution du DSFR se fait dans les gabarits.
 */
final class ContentRenderer
{
    private readonly HTML5 $html5;
    /** @var array<int, Media> médias du contenu en cours de rendu */
    private array $medias = [];
    /** @var list<array{id: string, text: string, level: int}> titres de section du contenu en cours de rendu */
    private array $headings = [];

    public function __construct(
        private readonly ComponentRegistry $registry,
        private readonly ContentSanitizer $sanitizer,
        private readonly MediaRepository $mediaRepository,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly SluggerInterface $slugger,
        private readonly DsfrArtwork $artwork,
    ) {
        $this->html5 = new HTML5(['disable_html_ns' => true]);
    }

    public function render(?string $content): string
    {
        return $this->renderDocument($content)['html'];
    }

    /**
     * Contenu rendu et sommaire : les titres de section (h2) et sous-titres (h3) du contenu, hors composants,
     * reçoivent une ancre (id) ; le sommaire les reprend, les sous-titres rangés sous leur section.
     *
     * @return array{html: string, summary: list<array{id: string, text: string, children: list<array{id: string, text: string}>}>}
     */
    public function renderDocument(?string $content): array
    {
        $clean = $this->sanitizer->sanitize($content);
        if (null === $clean) {
            return ['html' => '', 'summary' => []];
        }

        $fragment = $this->html5->loadHTMLFragment($clean);
        $this->medias = $this->mediaRepository->findByIds(self::mediaIds($fragment));
        $this->headings = [];

        try {
            return ['html' => $this->renderNodes($fragment->childNodes, true), 'summary' => self::summary($this->headings)];
        } finally {
            $this->medias = [];
            $this->headings = [];
        }
    }

    /**
     * @param \DOMNodeList<\DOMNode> $nodes
     */
    private function renderNodes(\DOMNodeList $nodes, bool $root = false): string
    {
        $html = '';
        $groupKey = null;
        $items = [];
        // Paragraphes vides (Entrée plusieurs fois : espace voulu par le rédacteur) : gardés, sauf entre deux composants
        // d'un même groupe (ils ne coupent pas un groupe d'accordéons, de cartes…) et en fin de contenu.
        $blanks = '';
        foreach (iterator_to_array($nodes) as $node) {
            if (self::isBlank($node)) {
                if ($node instanceof \DOMElement) {
                    $blanks .= '<p>&nbsp;</p>';
                }
                continue;
            }

            $component = $node instanceof \DOMElement && $node->hasAttribute('data-component') ? $this->component($node) : null;
            $key = $component ? $component['definition']->group : null;
            if (null !== $key && $key === $groupKey) {
                $blanks = '';
            }
            if ($key !== $groupKey) {
                $html .= $this->renderGroup($groupKey, $items);
                $groupKey = null;
                $items = [];
            }
            $html .= $blanks;
            $blanks = '';

            if (false === $component) {
                continue;
            }
            if (null === $component) {
                if ($root && $node instanceof \DOMElement && \in_array($node->nodeName, ['h2', 'h3'], true)) {
                    $this->anchor($node);
                }
                $html .= $this->html5->saveHTML($node);
                continue;
            }

            $component['html'] = $this->twig->render('@CkeditorDsfr/components/'.$component['name'].'.html.twig', ['c' => $component]);
            if (null === $key) {
                $html .= $component['html'];
            } else {
                $groupKey = $key;
                $items[] = $component;
            }
        }

        return $html.$this->renderGroup($groupKey, $items);
    }

    /**
     * @param list<array<string, mixed>> $items composants consécutifs du groupe
     */
    private function renderGroup(?string $key, array $items): string
    {
        return null === $key || [] === $items ? '' : $this->twig->render('@CkeditorDsfr/groups/'.$key.'.html.twig', ['items' => $items, 'id' => self::uid()]);
    }

    /**
     * Données d'un composant pour son gabarit, false s'il est inconnu ou incomplet (non affiché).
     *
     * @return array{name: string, definition: ComponentDefinition, values: array<string, ?string>, media: ?Media, medias: list<Media>, mediaSettings: array<int, array<string, string>>, slots: array<string, string>, columns: list<array{span: int, html: string}>, id: string}|false
     */
    private function component(\DOMElement $element): array|false
    {
        $definition = $this->registry->get($element->getAttribute('data-component'));
        if (null === $definition) {
            return false;
        }

        $values = [];
        $media = null;
        $medias = [];
        $mediaSettings = [];
        $fields = [];
        $raws = [];
        foreach ($definition->fields as $field) {
            $fields[$field->name] = $field;
            $raws[$field->name] = $element->getAttribute($field->attribute());
        }
        foreach ($definition->fields as $field) {
            $raw = $element->getAttribute($field->attribute());
            // Ancien composant « Téléchargement » à un seul document (data-media-id).
            if ('medias' === $field->type && '' === $raw) {
                $raw = $element->getAttribute('data-media-id');
            }
            $value = $this->value($definition, $field, $raw);
            if ('media' === $field->type && null !== $value) {
                $media = $this->medias[(int) $value] ?? null;
                if ($media?->getType() !== $field->mediaType) {
                    $media = $value = null;
                }
            }
            if ('medias' === $field->type && null !== $value) {
                // Médias existants et du bon type, dans l'ordre choisi.
                $medias = [];
                foreach (explode(',', $value) as $id) {
                    $candidate = $this->medias[(int) $id] ?? null;
                    if (null !== $candidate && $candidate->getType() === $field->mediaType) {
                        $medias[] = $candidate;
                    }
                }
                $value = [] === $medias ? null : implode(',', array_map(static fn (Media $m): int => (int) $m->getId(), $medias));
                $mediaSettings = $this->mediaSettings($definition, $field, $element->getAttribute($field->settingsAttribute()), $medias);
            }
            // Réglage obligatoire seulement s'il est proposé (ex. lien d'un bouton de carte seulement avec son libellé).
            if ($field->required && (null === $value || '' === $value) && $field->isShown($raws, $fields)) {
                $this->logger->info('Composant incomplet, non affiché.', ['component' => $definition->name, 'field' => $field->name]);

                return false;
            }
            $values[$field->name] = $value;
        }

        $columns = $definition->layout ? $this->columns($element, (string) $values['columns']) : [];
        if ($definition->layout && '' === implode('', array_column($columns, 'html'))) {
            return false;
        }

        $slots = [];
        foreach ($definition->layout ? [] : $definition->slots as $slot) {
            $slotElement = null;
            foreach ($element->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->getAttribute('data-slot') === $slot['name']) {
                    $slotElement = $child;
                    break;
                }
            }
            $slots[$slot['name']] = null === $slotElement ? '' : trim($slot['inline']
                ? implode('', array_map(fn (\DOMNode $n): string => $this->html5->saveHTML($n), iterator_to_array($slotElement->childNodes)))
                : $this->renderNodes($slotElement->childNodes));
            // Zone laissée vide dans l'éditeur (espace insécable, <br>) : vide, le gabarit n'affiche pas sa balise.
            if (self::isEmptyHtml($slots[$slot['name']])) {
                $slots[$slot['name']] = '';
            }
            if ($slot['required'] && '' === trim(strip_tags($slots[$slot['name']]))) {
                return false;
            }
        }

        return ['name' => $definition->name, 'definition' => $definition, 'values' => $values, 'media' => $media, 'medias' => $medias, 'mediaSettings' => $mediaSettings, 'slots' => $slots, 'columns' => $columns, 'id' => self::uid()];
    }

    /**
     * Réglages propres à chaque média (JSON {"12": {"pictogram": "…"}}), revalidés comme les autres : média du
     * composant, réglage déclaré, valeur permise ; le reste est ignoré.
     *
     * @param list<Media> $medias
     *
     * @return array<int, array<string, string>>
     */
    private function mediaSettings(ComponentDefinition $definition, ComponentField $field, string $json, array $medias): array
    {
        if ([] === $field->items || '' === $json) {
            return [];
        }
        $raw = json_decode($json, true);
        if (!\is_array($raw)) {
            return [];
        }
        $settings = [];
        foreach ($medias as $media) {
            $item = $raw[(string) $media->getId()] ?? null;
            if (!\is_array($item)) {
                continue;
            }
            foreach ($field->items as $itemField) {
                $value = \is_string($item[$itemField->name] ?? null) ? $this->value($definition, $itemField, $item[$itemField->name]) : null;
                // Liste de choix : une valeur invalide n'est pas remplacée par la valeur par défaut (le composant s'applique).
                if (null !== $value && '' !== $value && ('choice' !== $itemField->type || \array_key_exists(trim($item[$itemField->name]), $itemField->options))) {
                    $settings[(int) $media->getId()][$itemField->name] = $value;
                }
            }
        }

        return $settings;
    }

    /**
     * Colonnes d'un bloc de mise en page, une par largeur de la disposition (« 4-8 ») : une colonne en trop (HTML
     * modifié à la main) est ajoutée à la dernière, une colonne manquante est vide.
     *
     * @return list<array{span: int, html: string}>
     */
    private function columns(\DOMElement $element, string $layout): array
    {
        $spans = array_map(intval(...), explode('-', $layout));
        $html = array_fill(0, \count($spans), '');
        $index = 0;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && 'column' === $child->getAttribute('data-slot')) {
                $target = min($index++, \count($spans) - 1);
                $html[$target] = trim($html[$target].$this->renderNodes($child->childNodes));
            }
        }

        return array_map(static fn (int $span, string $content): array => ['span' => $span, 'html' => $content], $spans, $html);
    }

    /** Réglage validé selon son type, null s'il est absent ou invalide. */
    private function value(ComponentDefinition $definition, ComponentField $field, string $raw): ?string
    {
        $raw = trim($raw);

        return match ($field->type) {
            'choice' => \array_key_exists($raw, $field->options) ? $raw : $field->default,
            'media' => ctype_digit($raw) ? $raw : null,
            'icon' => $this->artwork->isIcon($raw) ? $raw : null,
            'pictogram' => $this->artwork->isPictogram($raw) ? $raw : null,
            'medias' => preg_match('/^\d+(,\d+){0,'.(ComponentField::MAX_MEDIAS - 1).'}$/', $raw) ? $raw : null,
            'url' => 'video' === $definition->name ? $this->videoUrl($raw) : self::safeUrl($raw),
            default => '' === $raw ? null : mb_substr($raw, 0, $field->maxLength),
        };
    }

    /** Lien autorisé : http(s), mailto, tel ou page du site (/…). */
    public static function safeUrl(string $url): ?string
    {
        if ('' === $url || preg_match('/[\x00-\x1F\s]/', $url)) {
            return null;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        return \in_array(strtolower((string) parse_url($url, \PHP_URL_SCHEME)), ['https', 'http', 'mailto', 'tel'], true) ? $url : null;
    }

    /**
     * Adresse du lecteur intégré : les liens de page YouTube, Dailymotion et Vimeo sont convertis en lecteur ;
     * l'hôte du lecteur doit être autorisé par l'administrateur (EditorSettings, HTTPS uniquement).
     */
    public function videoUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (!\is_array($parts) || 'https' !== strtolower($parts['scheme'] ?? '') || !isset($parts['host'])) {
            return null;
        }
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        $embed = match (true) {
            \in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true) && '/watch' === $path && \is_string($query['v'] ?? null) => 'https://www.youtube.com/embed/'.$query['v'],
            'youtu.be' === $host => 'https://www.youtube.com/embed/'.ltrim($path, '/'),
            \in_array($host, ['dailymotion.com', 'www.dailymotion.com'], true) && str_starts_with($path, '/video/') => 'https://www.dailymotion.com/embed/video/'.substr($path, 7),
            'dai.ly' === $host => 'https://www.dailymotion.com/embed/video/'.ltrim($path, '/'),
            \in_array($host, ['vimeo.com', 'www.vimeo.com'], true) && preg_match('~^/(\d+)~', $path, $m) => 'https://player.vimeo.com/video/'.$m[1],
            default => $url,
        };

        $embedHost = strtolower((string) parse_url($embed, \PHP_URL_HOST));

        return \in_array($embedHost, $this->registry->videoHosts(), true) && !preg_match('/[\x00-\x1F\s"<>]/', $embed) ? $embed : null;
    }

    /** Ancre unique d'un titre de section (« Contexte et enjeux » → section-contexte-et-enjeux), retenue pour le sommaire. */
    private function anchor(\DOMElement $heading): void
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $heading->textContent));
        if ('' === $text) {
            return;
        }
        $base = 'section-'.($this->slugger->slug($text)->lower()->truncate(60)->trim('-')->toString() ?: 'titre');
        $used = array_column($this->headings, 'id');
        $id = $base;
        for ($i = 2; \in_array($id, $used, true); ++$i) {
            $id = $base.'-'.$i;
        }
        $heading->setAttribute('id', $id);
        $this->headings[] = ['id' => $id, 'text' => $text, 'level' => 'h2' === $heading->nodeName ? 2 : 3];
    }

    /**
     * Sommaire hiérarchique : un sous-titre (h3) est rangé sous la section (h2) qui le précède.
     *
     * @param list<array{id: string, text: string, level: int}> $headings
     *
     * @return list<array{id: string, text: string, children: list<array{id: string, text: string}>}>
     */
    private static function summary(array $headings): array
    {
        $summary = [];
        foreach ($headings as $heading) {
            $last = array_key_last($summary);
            if (3 === $heading['level'] && null !== $last) {
                $summary[$last]['children'][] = ['id' => $heading['id'], 'text' => $heading['text']];
                continue;
            }
            $summary[] = ['id' => $heading['id'], 'text' => $heading['text'], 'children' => []];
        }

        return $summary;
    }

    /**
     * Identifiants des médias cités (data-media-id, data-media-ids) dans tout le contenu, composants imbriqués compris.
     *
     * @return list<int>
     */
    private static function mediaIds(\DOMNode $node): array
    {
        $ids = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if (ctype_digit($child->getAttribute('data-media-id'))) {
                    $ids[] = (int) $child->getAttribute('data-media-id');
                }
                foreach (explode(',', $child->getAttribute('data-media-ids')) as $id) {
                    if (ctype_digit($id)) {
                        $ids[] = (int) $id;
                    }
                }
                $ids = [...$ids, ...self::mediaIds($child)];
            }
        }

        return $ids;
    }

    /** HTML sans texte visible ni contenu non textuel (image, tableau, illustration, composant, bouton, lien). */
    private static function isEmptyHtml(string $html): bool
    {
        return '' === trim(html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{A0}")
            && !preg_match('/<(img|table|svg|iframe|dialog|button|hr|a)\b/i', $html);
    }

    private static function isBlank(\DOMNode $node): bool
    {
        if ($node instanceof \DOMText) {
            return '' === trim($node->textContent, " \t\n\r\0\x0B\u{A0}");
        }

        return $node instanceof \DOMElement && 'p' === strtolower($node->nodeName)
            && '' === trim($node->textContent, " \t\n\r\0\x0B\u{A0}");
    }

    private static function uid(): string
    {
        return 'cmp-'.bin2hex(random_bytes(4));
    }
}
