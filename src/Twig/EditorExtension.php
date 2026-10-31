<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Twig;

use Yassrzg\CkeditorDsfrBundle\CkeditorDsfrBundle;
use Yassrzg\CkeditorDsfrBundle\Editor\ComponentDefinition;
use Yassrzg\CkeditorDsfrBundle\Editor\ComponentRegistry;
use Yassrzg\CkeditorDsfrBundle\Editor\ContentRenderer;
use Yassrzg\CkeditorDsfrBundle\Editor\DsfrArtwork;
use Yassrzg\CkeditorDsfrBundle\Editor\PlainText;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * Fonctions Twig du bundle : affichage DSFR d'un contenu, description des composants pour l'éditeur, feuille de style.
 */
final readonly class EditorExtension
{
    public function __construct(
        private ContentRenderer $renderer,
        private ComponentRegistry $registry,
        private DsfrArtwork $artwork,
        private Packages $packages,
        private AuthorizationCheckerInterface $authorizationChecker,
        private string $layout,
        /** @var array{admin: string, settings: string, view: string} */
        private array $roles,
    ) {
    }

    /** Droit configuré (ckeditor_dsfr.roles) : {% if ckeditor_dsfr_is_granted('settings') %}. */
    #[AsTwigFunction('ckeditor_dsfr_is_granted')]
    public function isGranted(string $access): bool
    {
        return isset($this->roles[$access]) && $this->authorizationChecker->isGranted($this->roles[$access]);
    }

    /**
     * Contenu saisi avec l'éditeur, rendu en DSFR : {{ page.content|ckeditor_dsfr_render }}.
     * Une ancienne valeur en texte simple est affichée en paragraphes.
     */
    #[AsTwigFilter('ckeditor_dsfr_render', isSafe: ['html'])]
    public function render(?string $value): string
    {
        return PlainText::isPlainText($value) ? PlainText::toHtml($value) : $this->renderer->render($value);
    }

    /**
     * Contenu rendu et sommaire (titres de section avec leurs ancres) : {% set doc = ckeditor_dsfr_document(page.content) %}.
     *
     * @return array{html: string, summary: list<array{id: string, text: string, children: list<array{id: string, text: string}>}>}
     */
    #[AsTwigFunction('ckeditor_dsfr_document')]
    public function document(?string $content): array
    {
        return PlainText::isPlainText($content) ? ['html' => PlainText::toHtml($content), 'summary' => []] : $this->renderer->renderDocument($content);
    }

    /**
     * @return array<string, ComponentDefinition> composants proposés à l'insertion, ou tous (fenêtres de réglages des
     *                                            composants déjà présents dans un contenu)
     */
    #[AsTwigFunction('ckeditor_dsfr_components')]
    public function components(bool $insertableOnly = true): array
    {
        return $insertableOnly ? $this->registry->all() : $this->registry->definitions();
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[AsTwigFunction('ckeditor_dsfr_components_config')]
    public function config(): array
    {
        return $this->registry->forEditor();
    }

    #[AsTwigFunction('ckeditor_dsfr_pictogram_url')]
    public function pictogramUrl(string $pictogram): string
    {
        return $this->artwork->pictogramUrl($pictogram);
    }

    /** Feuille de style des composants (à placer dans le <head> des pages qui affichent un contenu de l'éditeur). */
    #[AsTwigFunction('ckeditor_dsfr_stylesheets', isSafe: ['html'])]
    public function stylesheets(): string
    {
        return '<link rel="stylesheet" href="'.htmlspecialchars($this->packages->getUrl(CkeditorDsfrBundle::ASSET_NAMESPACE.'/styles/ckeditor-dsfr.css'), \ENT_QUOTES).'">';
    }

    /** Gabarit étendu par les pages d'administration du bundle (ckeditor_dsfr.layout). */
    #[AsTwigFunction('ckeditor_dsfr_layout')]
    public function layout(): string
    {
        return $this->layout;
    }
}
