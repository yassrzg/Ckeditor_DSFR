<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Editor;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Filtre des contenus de l'éditeur (à l'enregistrement et à l'affichage) : texte mis en forme (paragraphes, titres,
 * gras, italique, listes, liens, séparateur) et composants (<div> avec les seuls attributs data-* du ComponentRegistry).
 * Aucun style, script ni attribut libre.
 */
final readonly class ContentSanitizer
{
    /** Un contenu peut être long (texte et transcriptions) : limite du filtre relevée (20 000 caractères par défaut). */
    private const int MAX_INPUT_LENGTH = 1_000_000;

    private HtmlSanitizer $sanitizer;

    public function __construct(ComponentRegistry $registry)
    {
        $config = (new HtmlSanitizerConfig())
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(self::MAX_INPUT_LENGTH)
            ->allowElement('a', ['href'])
            ->allowElement('div', $registry->attributes());
        foreach (['p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'hr', 'table', 'thead', 'tbody', 'tfoot', 'tr'] as $element) {
            $config = $config->allowElement($element);
        }
        // Cellules des tableaux (composant Tableau) : fusion de cellules et portée des en-têtes seulement.
        $config = $config->allowElement('th', ['colspan', 'rowspan', 'scope'])->allowElement('td', ['colspan', 'rowspan']);
        // Balises sans intérêt (mise en forme collée) : retirées, leur texte conservé.
        foreach (['span', 'font', 'u', 'section', 'article', 'figure', 'figcaption', 'blockquote'] as $element) {
            $config = $config->blockElement($element);
        }

        $this->sanitizer = new HtmlSanitizer($config);
    }

    /** HTML filtré, null si le contenu est vide (ni texte ni composant). */
    public function sanitize(?string $html): ?string
    {
        if (null === $html) {
            return null;
        }

        $clean = trim($this->sanitizer->sanitize($html));
        $isEmpty = '' === trim(html_entity_decode(strip_tags($clean), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{A0}")
            && !str_contains($clean, 'data-component=');

        return $isEmpty ? null : $clean;
    }
}
