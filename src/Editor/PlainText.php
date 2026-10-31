<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Editor;

/**
 * Valeurs saisies avant l'éditeur (texte simple, sans balise) : reprises en paragraphes, retours à la ligne gardés.
 * Un champ existant peut donc passer à l'éditeur sans migration de données.
 */
final class PlainText
{
    private function __construct()
    {
    }

    /**
     * Texte simple : ni balise ni entité HTML. Une phrase sans mise en forme enregistrée par l'éditeur ne contient
     * aucune balise mais des entités (ex. « l&#039;équipe ») : c'est du HTML, à ne pas échapper une seconde fois.
     */
    public static function isPlainText(?string $value): bool
    {
        return null === $value
            || (!preg_match('~<(?:p|br|h[234]|strong|b|em|i|ul|ol|li|a|div|span|hr)\b[^>]*>~i', $value)
                && !preg_match('~&(?:#\d+|#x[0-9a-f]+|[a-z]+);~i', $value));
    }

    /** Paragraphes HTML d'un texte simple (un paragraphe par bloc séparé d'une ligne vide). */
    public static function toHtml(?string $value): string
    {
        if (null === $value || '' === trim($value)) {
            return '';
        }

        $text = htmlspecialchars(trim(str_replace("\r\n", "\n", $value)), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $paragraphs = preg_split('~\n\s*\n~', $text) ?: [$text];

        return implode('', array_map(static fn (string $p): string => '<p>'.str_replace("\n", '<br>', trim($p)).'</p>', $paragraphs));
    }
}
