<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Tests\Functional;

use Yassrzg\CkeditorDsfrBundle\Editor\ContentRenderer;
use Yassrzg\CkeditorDsfrBundle\Editor\ContentSanitizer;
use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Yassrzg\CkeditorDsfrBundle\Settings\EditorSettings;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Rendu DSFR des contenus de l'éditeur : composants, variantes, groupes, sommaire, filtre HTML.
 */
final class RenderingTest extends FunctionalTestCase
{
    public function testComponentsAreRenderedWithDsfrMarkup(): void
    {
        $this->saveVideoHosts(['www.youtube.com', 'player.vimeo.com']);
        $image = $this->createMedia(MediaType::IMAGE, 'Schéma', 'image.png', 'Schéma du réseau');
        $guide = $this->createMedia(MediaType::DOCUMENT, 'Guide', 'document.pdf');
        $model = $this->createMedia(MediaType::DOCUMENT, 'Modèle', 'document.pdf');
        $i = $image->getId();

        $html = $this->render(
            '<div data-component="image" data-media-id="'.$i.'" data-ratio="4x3" data-size="sm" data-caption="Source : Service de communication"></div>'
            .'<div data-component="download" data-media-ids="'.$model->getId().','.$guide->getId().',999999" data-variant="link"></div>'
            .'<p>&nbsp;</p>'
            .'<div data-component="card" data-title="A" data-url="/apps/a" data-media-id="'.$i.'"></div>'
            .'<div data-component="card" data-title="B" data-url="https://b.example"></div>'
            .'<div data-component="card" data-title="C" data-url="/apps/c"></div>'
            .'<div data-component="download" data-media-ids="'.$guide->getId().','.$model->getId().'" data-variant="card"></div>'
            .'<div data-component="button" data-label="Un" data-url="/apps/un" data-style="secondary"></div>'
            .'<div data-component="button" data-label="Deux" data-url="mailto:a@b.fr" data-style="inconnu"></div>'
            .'<div data-component="accordion"><div data-slot="title">Question <strong>1</strong></div><div data-slot="body"><p>Réponse</p>'
            .'<div data-component="download" data-media-id="'.$guide->getId().'" data-variant="link"></div></div></div>'
            .'<div data-component="accordion"><div data-slot="title">Question 2</div><div data-slot="body"><p>Réponse 2</p></div></div>'
            .'<div data-component="accordion"><div data-slot="title"></div><div data-slot="body"><p>Sans titre</p></div></div>'
            .'<div data-component="tab"><div data-slot="title">Onglet 1</div><div data-slot="body"><p>Contenu 1</p></div></div>'
            .'<div data-component="tab"><div data-slot="title">Onglet 2</div><div data-slot="body"><p>Contenu 2</p></div></div>'
            .'<div data-component="callout" data-color="green-emeraude"><div data-slot="title">À retenir</div><div data-slot="body"><p>Important</p></div></div>'
            .'<div data-component="callout" data-color="rouge-inconnu"><div data-slot="title"></div><div data-slot="body"><p>Sans titre</p></div></div>'
            .'<div data-component="highlight" data-color="yellow-moutarde"><div data-slot="body"><p>Exergue</p></div></div>'
            .'<div data-component="alert" data-type="warning"><div data-slot="title"></div><div data-slot="body"><p>Attention</p></div></div>'
            .'<div data-component="quote" data-author="Auteur" data-source="Rapport"><div data-slot="body"><p>Citation</p></div></div>'
            .'<div data-component="video" data-url="https://www.youtube.com/watch?v=abc123" data-title="Présentation" data-transcription="Bonjour"></div>'
            .'<div data-component="video" data-url="https://evil.example/v.mp4" data-title="Refusée"></div>'
            .'<div data-component="callout" data-icon="fr-icon-warning-line"><div data-slot="body"><p>Avec icône</p></div></div>'
            .'<div data-component="callout" data-icon="fr-icon-inexistante"><div data-slot="body"><p>Icône inconnue</p></div></div>'
            .'<div data-component="tile" data-title="Annuaire" data-url="/apps/" data-pictogram="digital/search"></div>'
            .'<div data-component="tile" data-title="Sans pictogramme" data-url="/apps/" data-pictogram="../../secret"></div>'
            .'<p>Séparation</p>'
            .'<div data-component="button" data-label="Télécharger" data-url="/apps/x" data-icon="fr-icon-download-line" data-icon-position="right"></div>'
            .'<div data-component="media-text" data-media-id="'.$i.'" data-position="right" data-size="sm" data-ratio="1x1"><div data-slot="body"><p>Texte à côté</p></div></div>'
            .'<div data-component="inconnu"></div>',
        );
        $page = new Crawler('<div id="c">'.$html.'</div>');

        $img = $page->filter('figure.fr-content-media.cmp-image--sm img');
        self::assertStringContainsString('fr-ratio-4x3', (string) $img->attr('class'));
        self::assertSame('Schéma du réseau', $img->attr('alt'), 'Texte alternatif de la médiathèque.');
        self::assertStringContainsString('/media/'.$i, (string) $img->attr('src'));
        self::assertSame('Source : Service de communication', $page->filter('.fr-content-media__caption')->first()->text());

        self::assertCount(1, $page->filter('.fr-callout.fr-icon-warning-line'), 'Icône du DSFR.');
        self::assertCount(0, $page->filter('.fr-callout.fr-icon-inexistante'), 'Icône inconnue ignorée.');
        self::assertStringContainsString('digital/search', (string) $page->filter('.fr-tile .fr-tile__pictogram use')->attr('href'), 'Pictogramme du DSFR.');
        self::assertCount(1, $page->filter('.fr-tile:contains("Sans pictogramme")'));
        self::assertCount(1, $page->filter('.fr-tile__pictogram'), 'Chemin de pictogramme inconnu ignoré.');
        self::assertCount(1, $page->filter('a.fr-btn.fr-btn--icon-right.fr-icon-download-line'), 'Bouton avec icône à droite, libellé gardé.');

        $mediaText = $page->filter('.cmp-media-text.cmp-media-text--right');
        self::assertCount(1, $mediaText, 'Image et texte : image à droite.');
        self::assertStringContainsString('fr-col-md-4', (string) $mediaText->filter('.cmp-media-text__media')->attr('class'), 'Image sur un tiers.');
        self::assertStringContainsString('fr-col-md-8', (string) $mediaText->filter('.cmp-media-text__text')->attr('class'));
        self::assertSame('Texte à côté', $mediaText->filter('.cmp-media-text__text p')->text());

        $downloads = $page->filter('.fr-downloads-group a.fr-link--download');
        self::assertCount(2, $downloads, 'Un composant, deux documents (média inconnu ignoré) : un groupe de liens.');
        self::assertStringStartsWith('Modèle', $downloads->first()->text(), 'Ordre choisi.');
        self::assertMatchesRegularExpression('/^PDF – \d+ (o|Ko)$/', $downloads->first()->filter('.fr-link__detail')->text());

        $grids = $page->filter('ul.fr-grid-row');
        self::assertCount(3, $grids->first()->filter('li'), 'Trois cartes consécutives : une grille.');
        self::assertStringContainsString('fr-col-md-4', (string) $grids->first()->filter('li')->first()->attr('class'), '3 cartes : 3 colonnes.');
        self::assertCount(2, $page->filter('.fr-card--download'), 'Documents en cartes : leur propre grille.');
        self::assertSame('Guide', $page->filter('.fr-card--download .fr-card__title')->first()->text());

        self::assertSame(['fr-btn fr-btn--secondary', 'fr-btn'], $page->filter('.fr-btns-group a:not(.fr-icon-download-line)')->each(static fn (Crawler $a): string => (string) $a->attr('class')), 'Style inconnu : principal.');

        $accordions = $page->filter('.fr-accordions-group > .fr-accordion');
        self::assertCount(2, $accordions, 'Accordéon sans titre non affiché.');
        $button = $accordions->first()->filter('.fr-accordion__btn');
        self::assertSame('Question 1', $button->text(), 'Titre en texte simple (pas de balise dans le bouton).');
        self::assertCount(1, $page->filter('#'.$button->attr('aria-controls').'.fr-collapse'));
        self::assertCount(1, $accordions->first()->filter('.fr-collapse a.fr-link--download'), 'Composant atomique dans un accordéon.');

        self::assertSame(['Onglet 1', 'Onglet 2'], $page->filter('.fr-tabs [role="tab"]')->each(static fn (Crawler $t): string => $t->text()));
        self::assertSame('true', $page->filter('.fr-tabs [role="tab"]')->first()->attr('aria-selected'));
        self::assertCount(2, $page->filter('.fr-tabs [role="tabpanel"]'));

        self::assertSame('À retenir', $page->filter('.fr-callout .fr-callout__title')->text());
        self::assertCount(1, $page->filter('.fr-callout.fr-callout--green-emeraude'), 'Couleur DSFR choisie.');
        $plain = $page->filter('.fr-callout')->eq(1);
        self::assertSame('fr-callout fr-mb-6v', $plain->attr('class'), 'Couleur inconnue : bleu France par défaut.');
        self::assertCount(0, $plain->filter('.fr-callout__title'), 'Titre facultatif.');
        self::assertSame('Exergue', $page->filter('.fr-highlight')->text());
        self::assertCount(1, $page->filter('.fr-highlight.fr-highlight--yellow-moutarde'));
        self::assertStringContainsString('fr-alert--warning fr-alert--sm', (string) $page->filter('.fr-alert')->attr('class'), 'Alerte sans titre : variante compacte.');
        self::assertSame('Auteur', $page->filter('.fr-quote .fr-quote__author')->text());

        $iframes = $page->filter('iframe.fr-responsive-vid');
        self::assertCount(1, $iframes, 'Vidéo d\'un hôte non autorisé : non affichée.');
        self::assertSame('https://www.youtube.com/embed/abc123', $iframes->attr('src'));
        self::assertSame('Présentation', $iframes->attr('title'));
        self::assertSame('Bonjour', $page->filter('.fr-transcription .fr-modal__content div')->text());

        // Média supprimé : le composant n'est plus affiché.
        $this->em->remove($this->em->find(Media::class, $i));
        $this->em->flush();
        self::assertSame('', $this->render('<div data-component="image" data-media-id="'.$i.'" data-ratio="4x3"></div>'));
    }

    public function testComponentVariantsAreRenderedWithDsfrClasses(): void
    {
        $guide = $this->createMedia(MediaType::DOCUMENT, 'Guide', 'document.pdf');
        $model = $this->createMedia(MediaType::DOCUMENT, 'Modèle', 'document.pdf');
        $ids = $guide->getId().','.$model->getId();

        $page = new Crawler('<div>'.$this->render(
            '<div data-component="download" data-media-ids="'.$ids.'" data-variant="tile" data-orientation="horizontal" data-size="sm" data-surface="grey"></div>'
            .'<p>Séparation</p>'
            .'<div data-component="download" data-media-ids="'.$guide->getId().'" data-variant="card" data-orientation="horizontal" data-size="lg" data-surface="inconnue" data-pictogram="document/document-download"></div>'
            .'<div data-component="card" data-title="A" data-url="/apps/a" data-orientation="horizontal-tier" data-surface="shadow"></div>'
            .'<div data-component="tile" data-title="T" data-url="/apps/t" data-pictogram="system/warning" data-size="lg"></div>'
            .'<div data-component="button" data-label="Grand" data-url="/apps/g" data-size="lg" data-style="tertiary-no-outline"></div>'
            .'<div data-component="quote" data-color="blue-ecume"><div data-slot="body"><p>Citation</p></div></div>'
            .'<div data-component="download" data-media-ids="'.$guide->getId().'" data-variant="tile" data-orientation="vertical"></div>'
        ).'</div>');

        $tiles = $page->filter('.fr-tile.fr-tile--download');
        self::assertCount(3, $tiles, 'Documents en tuiles de téléchargement.');
        // Tuile de téléchargement horizontale par défaut dans le DSFR : « verticale » à partir de la tablette.
        self::assertSame('fr-tile fr-enlarge-link fr-tile--download fr-tile--vertical@md', $tiles->last()->attr('class'));
        self::assertSame('fr-tile fr-enlarge-link fr-tile--download fr-tile--horizontal fr-tile--sm fr-tile--grey', $tiles->first()->attr('class'));
        self::assertStringContainsString('document/document-download', (string) $tiles->first()->filter('.fr-tile__pictogram use')->attr('href'), 'Pictogramme par défaut.');
        self::assertNotNull($tiles->first()->filter('a')->attr('download'));
        self::assertMatchesRegularExpression('/^PDF – /', $tiles->first()->filter('.fr-tile__detail')->text());

        self::assertSame('fr-card fr-enlarge-link fr-card--download fr-card--horizontal fr-card--lg', $page->filter('.fr-card--download')->attr('class'), 'Apparence inconnue : par défaut.');
        self::assertStringContainsString('document/document-download', (string) $page->filter('.fr-card--download .cmp-card__pictogram use')->attr('href'), 'Pictogramme d\'une carte de téléchargement.');
        self::assertCount(1, $page->filter('.fr-card.fr-card--horizontal.fr-card--horizontal-tier.fr-card--shadow'));
        self::assertStringContainsString('fr-col-md-6', (string) $page->filter('.fr-card--horizontal-tier')->ancestors()->filter('li')->attr('class'), 'Cartes horizontales : 2 colonnes au plus.');
        self::assertStringNotContainsString('fr-tile--lg', (string) $page->filter('.fr-tile:not(.fr-tile--download)')->attr('class'), 'Taille de tuile inconnue : moyenne.');
        self::assertCount(1, $page->filter('a.fr-btn.fr-btn--tertiary-no-outline.fr-btn--lg'));
        self::assertCount(1, $page->filter('figure.fr-quote.fr-quote--blue-ecume'));
    }

    public function testSummaryLinksToSectionTitles(): void
    {
        $twig = static::getContainer()->get('twig');
        $document = $twig->createTemplate('{% set d = ckeditor_dsfr_document(content) %}{{ d.html|raw }}{% if d.summary is not empty %}{{ include("@CkeditorDsfr/_summary.html.twig", {summary: d.summary, id: "s"}) }}{% endif %}')->render(['content' =>
            '<h2>Contexte et enjeux</h2><p>Texte</p><h3>Détail <em>technique</em></h3><p>Texte</p>'
            .'<div data-component="callout"><div data-slot="title">Encadré</div><div data-slot="body"><h3>Titre interne</h3></div></div>'
            .'<h2>Contexte et enjeux</h2><p>Suite</p>',
        ]);
        $page = new Crawler('<div>'.$document.'</div>');
        $summary = $page->filter('nav.fr-summary');
        self::assertSame('false', $summary->filter('button[aria-expanded]')->attr('aria-expanded'), 'Sommaire replié par défaut.');
        $links = $summary->filter('a.fr-summary__link');
        self::assertSame(['Contexte et enjeux', 'Détail technique', 'Contexte et enjeux'], $links->each(static fn (Crawler $a): string => $a->text()), 'Titres des composants exclus.');
        self::assertSame(['#section-contexte-et-enjeux', '#section-detail-technique', '#section-contexte-et-enjeux-2'], $links->each(static fn (Crawler $a): string => (string) $a->attr('href')), 'Ancres uniques.');
        self::assertCount(1, $summary->filter('ol > li > ol > li'), 'Sous-titre rangé sous sa section.');
        self::assertCount(1, $page->filter('h2#section-contexte-et-enjeux-2'));
    }

    public function testContentIsSanitizedAndPlainTextIsKept(): void
    {
        $sanitizer = static::getContainer()->get(ContentSanitizer::class);
        $content = (string) $sanitizer->sanitize(
            '<p onclick="alert(1)" style="color:red">Texte<script>alert(1)</script></p>'
            .'<div data-component="button" data-label="Go" data-url="javascript:alert(1)" data-style="primary" data-evil="x" class="x"></div>'
            .'<a href="javascript:alert(1)">lien</a><iframe src="https://evil.example"></iframe>',
        );
        foreach (['onclick', ' style=', '<script', 'data-evil', 'class=', 'href="javascript:', '<iframe'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $content);
        }
        self::assertStringContainsString('data-component="button"', $content);
        self::assertStringNotContainsString('javascript:', $this->render($content));
        self::assertSame('', $this->render('<div data-component="button" data-label="Go" data-url="javascript:alert(1)"></div>'), 'Bouton sans lien valide : non affiché.');
        self::assertNull($sanitizer->sanitize('<p>&nbsp;</p>'), 'Contenu vide.');

        $twig = static::getContainer()->get('twig');
        self::assertSame('<p>Ligne 1<br>Ligne &lt;2&gt;</p><p>Suite</p>', $twig->createTemplate('{{ v|ckeditor_dsfr_render }}')->render(['v' => "Ligne 1\nLigne <2>\n\nSuite"]), 'Ancien texte simple : paragraphes, échappé.');

        $text = static::getContainer()->get('test.content_text')->plain('<h2>Titre</h2><div data-component="card" data-title="Carte" data-url="/x"></div><p>Fin</p>');
        self::assertSame("Titre\nCarte\nFin", $text, 'Texte brut pour un moteur de recherche (réglages textuels compris).');
    }

    public function testNewDsfrComponentsAreRendered(): void
    {
        $image = $this->createMedia(MediaType::IMAGE, 'Portrait', 'image.png', 'Portrait');
        $guide = $this->createMedia(MediaType::DOCUMENT, 'Guide', 'document.pdf');
        $model = $this->createMedia(MediaType::DOCUMENT, 'Modèle', 'document.pdf');
        $settings = htmlspecialchars((string) json_encode([(string) $guide->getId() => ['pictogram' => 'system/warning', 'surface' => 'shadow'], (string) $model->getId() => ['surface' => 'inconnue']]));

        $page = new Crawler('<div id="c">'.$this->render(
            '<div data-component="notice" data-type="warning" data-title="Maintenance" data-description="Samedi" data-url="/apps/info" data-icon="no"></div>'
            .'<div data-component="notice" data-type="cyberattack" data-title="Attaque" data-icon="no"></div>'
            .'<div data-component="badge" data-label="Nouveau" data-type="new" data-size="sm"></div><div data-component="badge" data-label="Validé" data-type="success" data-icon="no"></div>'
            .'<p>Séparation</p>'
            .'<div data-component="tag" data-label="Réquisition" data-url="/apps/tag" data-color="green-menthe"></div><div data-component="tag" data-label="Statique" data-icon="fr-icon-warning-line" data-color="green-menthe"></div>'
            .'<div data-component="table" data-caption="Délais &lt;moyens&gt;" data-caption-display="bottom" data-bordered="yes" data-size="sm" data-color="blue-ecume">'
            .'<div data-slot="body"><table><thead><tr><th>Opérateur</th><th>Délai</th></tr></thead><tbody><tr><td colspan="2">Aucun</td></tr></tbody></table></div></div>'
            .'<div data-component="modal-button" data-label="Ouvrir" data-style="secondary" data-modal-size="lg"><div data-slot="title">Détails</div><div data-slot="body"><p>Contenu de la fenêtre</p></div></div>'
            .'<div data-component="quote" data-author="Auteur" data-source="Rapport" data-source-url="https://example.org/rapport" data-details="2026" data-media-id="'.$image->getId().'"><div data-slot="body"><p>Citation</p></div></div>'
            .'<div data-component="card" data-title="Carte" data-url="/apps/c" data-badge="Nouveau" data-badge-type="new"></div>'
            .'<div data-component="download" data-media-ids="'.$guide->getId().','.$model->getId().'" data-variant="tile" data-surface="grey" data-media-ids-settings="'.$settings.'"></div>',
        ).'</div>');

        $notice = $page->filter('.fr-notice')->first();
        self::assertSame('fr-notice fr-notice--warning fr-notice--no-icon fr-mb-6v', $notice->attr('class'));
        self::assertSame('Maintenance', $notice->filter('.fr-notice__title')->text());
        self::assertSame('En savoir plus', $notice->filter('a.fr-notice__link[href="/apps/info"]')->text(), 'Libellé du lien par défaut.');
        self::assertStringNotContainsString('no-icon', (string) $page->filter('.fr-notice--cyberattack')->attr('class'), 'Bandeau d\'alerte spécifique : icône obligatoire.');

        self::assertSame(['fr-badge fr-badge--new fr-badge--sm', 'fr-badge fr-badge--success fr-badge--no-icon'], $page->filter('#c > .fr-badges-group .fr-badge')->each(static fn (Crawler $b): string => (string) $b->attr('class')));
        self::assertSame('fr-tag fr-tag--green-menthe', $page->filter('.fr-tags-group a.fr-tag[href="/apps/tag"]')->attr('class'));
        self::assertSame('fr-tag fr-tag--icon-left fr-icon-warning-line', $page->filter('.fr-tags-group p.fr-tag')->attr('class'), 'Tag sans lien : pas de couleur.');

        $table = $page->filter('.fr-table');
        self::assertSame('fr-table fr-table--sm fr-table--bordered fr-table--blue-ecume fr-table--caption-bottom fr-mb-6v', $table->attr('class'));
        self::assertSame('Délais <moyens>', $table->filter('.fr-table__content table > caption')->text(), 'Titre du tableau échappé.');
        self::assertSame('2', $table->filter('td')->attr('colspan'));

        $id = (string) $page->filter('button.fr-btn--secondary[data-fr-opened="false"]')->attr('aria-controls');
        $modal = $page->filter('dialog#'.$id.'.fr-modal');
        self::assertCount(1, $modal);
        self::assertSame('Détails', $modal->filter('#'.$id.'-title')->text());
        self::assertStringContainsString('fr-col-lg-8', (string) $modal->filter('.fr-grid-row > div')->attr('class'));
        self::assertCount(1, $page->filter('.fr-btns-group button[aria-controls="'.$id.'"]'), 'Bouton avec fenêtre groupé avec les boutons.');
        self::assertCount(0, $page->filter('.fr-btns-group dialog'), 'Fenêtre hors du groupe de boutons (règles du groupe non appliquées à ses boutons).');

        $quote = $page->filter('figure.fr-quote.fr-quote--column');
        self::assertSame('https://example.org/rapport', $quote->filter('blockquote')->attr('cite'));
        self::assertSame(['Rapport', '2026'], $quote->filter('.fr-quote__source li')->each(static fn (Crawler $li): string => $li->text()));
        self::assertCount(1, $quote->filter('figcaption .fr-quote__image img[alt=""]'));

        self::assertSame('Nouveau', $page->filter('.fr-card .fr-card__start .fr-badge--new')->text());

        $tiles = $page->filter('.fr-tile--download');
        self::assertStringContainsString('fr-tile--shadow', (string) $tiles->eq(0)->attr('class'), 'Apparence propre au document.');
        self::assertStringContainsString('system/warning', (string) $tiles->eq(0)->filter('.fr-tile__pictogram use')->attr('href'), 'Pictogramme propre au document.');
        self::assertStringContainsString('fr-tile--grey', (string) $tiles->eq(1)->attr('class'), 'Valeur inconnue : réglage du composant.');
        self::assertStringContainsString('document/document-download', (string) $tiles->eq(1)->filter('.fr-tile__pictogram use')->attr('href'));
    }

    public function testEmptyOptionalPartsProduceNoMarkup(): void
    {
        $page = new Crawler('<div id="c">'.$this->render(
            '<div data-component="alert" data-type="info"><div data-slot="title">&nbsp;</div><div data-slot="body"><p>Message</p></div></div>'
            .'<div data-component="callout"><div data-slot="title"><br></div><div data-slot="body"><p>&nbsp;</p></div></div>'
            .'<div data-component="quote"><div data-slot="body"><p>&nbsp;</p></div></div>'
            .'<div data-component="card" data-title="Sans lien"></div>'
            .'<div data-component="card" data-title="Avec lien" data-url="/apps/carte"></div>'
            .'<p>Séparation</p>'
            .'<div data-component="icon" data-icon="fr-icon-warning-line" data-size="lg" data-align="center" data-alt="Attention"></div>'
            .'<div data-component="icon" data-icon="fr-icon-warning-line" data-text="Point important" data-alt="Ignoré"></div>'
            .'<div data-component="pictogram" data-pictogram="digital/search" data-size="lg" data-alt="Recherche"></div>'
            .'<div data-component="pictogram" data-pictogram="digital/search"></div>',
        ).'</div>');

        $alert = $page->filter('.fr-alert');
        self::assertCount(0, $alert->filter('.fr-alert__title'), 'Titre vide (espace insécable) : pas de balise de titre.');
        self::assertStringContainsString('fr-alert--sm', (string) $alert->attr('class'));
        self::assertCount(0, $page->filter('.fr-callout__title, .fr-callout__text'), 'Mise en avant : titre et texte vides non affichés.');
        self::assertCount(0, $page->filter('.fr-quote'), 'Citation sans texte non affichée.');

        $cards = $page->filter('.fr-card');
        self::assertCount(2, $cards);
        self::assertSame('fr-card', $cards->eq(0)->attr('class'), 'Carte sans lien : pas entièrement cliquable.');
        self::assertCount(0, $cards->eq(0)->filter('a'));
        self::assertSame('Sans lien', $cards->eq(0)->filter('.fr-card__title')->text());
        self::assertStringContainsString('fr-enlarge-link', (string) $cards->eq(1)->attr('class'));
        self::assertSame('/apps/carte', $cards->eq(1)->filter('.fr-card__title a')->attr('href'));

        $icons = $page->filter('p.cmp-artwork');
        self::assertSame('fr-icon-warning-line fr-icon--lg', $icons->eq(0)->filter('span')->attr('class'));
        self::assertSame('Attention', $icons->eq(0)->filter('.fr-sr-only')->text());
        self::assertStringContainsString('cmp-artwork--center', (string) $icons->eq(0)->attr('class'));
        self::assertSame('Point important', $icons->eq(1)->text());
        self::assertCount(0, $icons->eq(1)->filter('.fr-sr-only'), 'Texte visible : pas de texte alternatif en double.');

        $pictograms = $page->filter('div.cmp-artwork svg.fr-artwork');
        self::assertSame(['img', 'Recherche', '120px'], [$pictograms->eq(0)->attr('role'), $pictograms->eq(0)->attr('aria-label'), $pictograms->eq(0)->attr('width')]);
        self::assertSame('true', $pictograms->eq(1)->attr('aria-hidden'), 'Sans texte alternatif : décoratif.');
    }

    public function testColumnsAreRenderedOnTheDsfrGrid(): void
    {
        $image = $this->createMedia(MediaType::IMAGE, 'Schéma', 'image.png', 'Schéma du réseau');
        $guide = $this->createMedia(MediaType::DOCUMENT, 'Guide', 'document.pdf');

        $page = new Crawler('<div id="c">'.$this->render(
            '<div data-component="columns" data-columns="3-9" data-breakpoint="md" data-align="middle">'
            .'<div data-slot="column"><div data-component="image" data-media-id="'.$image->getId().'" data-ratio="1x1" data-size="lg"></div></div>'
            .'<div data-slot="column"><h2>Titre</h2><p>Texte</p>'
            .'<div data-component="card" data-title="A" data-url="/a"></div><div data-component="card" data-title="B" data-url="/b"></div>'
            .'<div data-component="accordion"><div data-slot="title">Question</div><div data-slot="body">'
            .'<div data-component="download" data-media-ids="'.$guide->getId().'" data-variant="link"></div></div></div></div></div>'
            .'<div data-component="columns" data-columns="4-4-4" data-breakpoint="lg">'
            .'<div data-slot="column"><p>Un</p></div><div data-slot="column"><p>Deux</p></div><div data-slot="column"><p>Trois</p></div>'
            .'<div data-slot="column"><p>En trop</p></div></div>'
            .'<div data-component="columns" data-columns="1-11" data-breakpoint="xl"><div data-slot="column"><p>A</p></div><div data-slot="column"><p>B</p></div></div>'
            .'<div data-component="columns" data-columns="6-6"><div data-slot="column"><p></p></div><div data-slot="column"></div></div>',
        ).'</div>');

        $rows = $page->filter('#c > .fr-grid-row.cmp-columns');
        self::assertCount(3, $rows, 'Bloc sans contenu non affiché.');
        $first = $rows->eq(0);
        self::assertStringContainsString('fr-grid-row--gutters', (string) $first->attr('class'));
        self::assertStringContainsString('fr-grid-row--middle', (string) $first->attr('class'));
        self::assertSame(
            ['fr-col-12 fr-col-md-3 cmp-columns__column', 'fr-col-12 fr-col-md-9 cmp-columns__column'],
            $first->children()->each(static fn (Crawler $c): string => (string) $c->attr('class')),
            'Pleine largeur sur téléphone, 3 + 9 à partir de la tablette.',
        );
        self::assertSame('Schéma du réseau', $first->children()->eq(0)->filter('img')->attr('alt'), 'Média de la colonne rendu.');
        $text = $first->children()->eq(1);
        self::assertSame('Titre', $text->filter('h2')->text());
        self::assertCount(2, $text->filter('.fr-grid-row .fr-card'), 'Cartes consécutives de la colonne groupées en grille.');
        self::assertCount(1, $text->filter('.fr-accordion a[href*="/media/'.$guide->getId().'/telecharger"]'), 'Composant dans un composant de la colonne.');

        $second = $rows->eq(1);
        self::assertStringNotContainsString('fr-grid-row--top', (string) $second->attr('class'));
        self::assertSame(['fr-col-12 fr-col-lg-4 cmp-columns__column'], array_values(array_unique($second->children()->each(static fn (Crawler $c): string => (string) $c->attr('class')))));
        self::assertSame(['Un', 'Deux', 'TroisEn trop'], $second->children()->each(static fn (Crawler $c): string => $c->text()), 'Colonne en trop ajoutée à la dernière.');

        self::assertSame(
            ['fr-col-12 fr-col-md-6 cmp-columns__column', 'fr-col-12 fr-col-md-6 cmp-columns__column'],
            $rows->eq(2)->children()->each(static fn (Crawler $c): string => (string) $c->attr('class')),
            'Disposition et seuil inconnus : valeurs par défaut.',
        );
    }

    public function testDisabledComponentIsStillRendered(): void
    {
        static::getContainer()->get(EditorSettings::class)->save([
            'video_hosts' => [], 'image_extensions' => ['png'], 'document_extensions' => ['pdf'], 'image_max_size' => 10,
            'document_max_size' => 50, 'disabled_components' => ['alert'], 'image_directory' => 'image', 'document_directory' => 'document',
        ]);
        self::assertStringContainsString('fr-alert--info', $this->render('<div data-component="alert" data-type="info"><div data-slot="body"><p>Texte</p></div></div>'), 'Contenu existant toujours affiché.');
    }

    /**
     * @param list<string> $hosts
     */
    private function saveVideoHosts(array $hosts): void
    {
        $settings = static::getContainer()->get(EditorSettings::class);
        $settings->save([
            'video_hosts' => $hosts, 'image_extensions' => ['jpg', 'png'], 'document_extensions' => ['pdf'], 'image_max_size' => 10,
            'document_max_size' => 50, 'disabled_components' => [], 'image_directory' => 'image', 'document_directory' => 'document',
        ]);
        static::getContainer()->get('ckeditor_dsfr.component_registry')->reset();
    }

    private function render(string $content): string
    {
        return trim(static::getContainer()->get(ContentRenderer::class)->render($content));
    }
}
