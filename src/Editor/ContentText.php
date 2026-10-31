<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Editor;

use Masterminds\HTML5;

/**
 * Texte brut d'un contenu de l'éditeur, pour un moteur de recherche : paragraphes, titres, zones des composants
 * (accordéons, onglets…) et réglages textuels des composants (titre d'une carte, légende, transcription d'une vidéo…).
 */
final readonly class ContentText
{
    /** Éléments qui terminent une ligne de texte. */
    private const array BLOCKS = ['p', 'div', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'br', 'hr', 'td', 'th'];

    private HTML5 $html5;

    public function __construct(private ComponentRegistry $registry)
    {
        $this->html5 = new HTML5(['disable_html_ns' => true]);
    }

    public function plain(?string $html): string
    {
        if (null === $html || '' === trim($html)) {
            return '';
        }

        $text = $this->collect($this->html5->loadHTMLFragment($html), $this->registry->textAttributes());
        $text = (string) preg_replace('/[ \t\x{A0}]+/u', ' ', html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));

        return trim((string) preg_replace('/\s*\n\s*/', "\n", $text));
    }

    /**
     * @param list<string> $attributes réglages textuels des composants
     */
    private function collect(\DOMNode $node, array $attributes): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $text .= $child->textContent;
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            foreach ($attributes as $attribute) {
                if ('' !== trim($child->getAttribute($attribute))) {
                    $text .= "\n".$child->getAttribute($attribute)."\n";
                }
            }
            $block = \in_array(strtolower($child->nodeName), self::BLOCKS, true);
            $text .= ($block ? "\n" : '').$this->collect($child, $attributes).($block ? "\n" : '');
        }

        return $text;
    }
}
