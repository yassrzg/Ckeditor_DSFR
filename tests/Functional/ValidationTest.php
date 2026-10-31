<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Tests\Functional;

use Yassrzg\CkeditorDsfrBundle\Editor\ContentValidator;
use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Yassrzg\CkeditorDsfrBundle\Form\EditorType;

/**
 * Contrôle serveur d'un contenu envoyé (requête forgée ou HTML modifié à la main) : composants, valeurs, médias,
 * imbrication et colonnes. Un contenu produit par l'éditeur est accepté.
 */
final class ValidationTest extends FunctionalTestCase
{
    public function testContentProducedByTheEditorIsAccepted(): void
    {
        $image = $this->createMedia(MediaType::IMAGE, 'Schéma', 'image.png', 'Schéma');
        $guide = $this->createMedia(MediaType::DOCUMENT, 'Guide', 'document.pdf');

        self::assertSame([], $this->validator()->validate(
            '<h2>Titre</h2><p>Texte</p>'
            .'<div data-component="columns" data-columns="4-8" data-breakpoint="md" data-align="top">'
            .'<div data-slot="column"><div data-component="image" data-media-id="'.$image->getId().'" data-ratio="1x1"></div></div>'
            .'<div data-slot="column"><div data-component="accordion"><div data-slot="title">Question</div><div data-slot="body">'
            .'<div data-component="download" data-media-ids="'.$guide->getId().'" data-variant="link"></div></div></div>'
            .'<div data-component="card" data-title="A" data-url="/a" data-icon="fr-icon-warning-line" data-detail="D"></div>'
            .'<div data-component="tile" data-title="B" data-url="https://b.example" data-pictogram="digital/search"></div></div></div>'
            .'<div data-component="media-text" data-media-id="'.$image->getId().'"><div data-slot="body"><p>Ancien</p></div></div>'
            .'<div data-component="download" data-media-id="'.$guide->getId().'"></div>'
            .'<div data-component="download" data-media-ids="'.$guide->getId().'" data-variant="tile" data-media-ids-settings="'.htmlspecialchars('{"'.$guide->getId().'":{"pictogram":"system/warning","surface":"grey"}}').'"></div>'
            .'<div data-component="table" data-caption="T"><div data-slot="body"><table><tbody><tr><td>1</td></tr></tbody></table></div></div>'
            // Tous les composants dans une zone de contenu : tableau, colonnes et accordéon dans un accordéon.
            .'<div data-component="accordion"><div data-slot="title">Q</div><div data-slot="body">'
            .'<div data-component="table" data-caption="T"><div data-slot="body"><table><tbody><tr><td>1</td></tr></tbody></table></div></div>'
            .'<div data-component="columns" data-columns="4-8"><div data-slot="column"><div data-component="image" data-media-id="'.$image->getId().'"></div></div>'
            .'<div data-slot="column"><div data-component="columns" data-columns="6-6"><div data-slot="column"><p>A</p></div><div data-slot="column"><p>B</p></div></div></div></div>'
            .'<div data-component="accordion"><div data-slot="title">Q2</div><div data-slot="body"><p>R2</p></div></div></div></div>',
        ));
        self::assertSame([], $this->validator()->validate(null));
    }

    public function testForgedContentIsRefused(): void
    {
        $image = $this->createMedia(MediaType::IMAGE, 'Schéma', 'image.png', 'Schéma');

        $errors = $this->validator()->validate(
            '<div data-component="inconnu"></div>'
            .'<div data-component="alert" data-type="fatal"><div data-slot="body"><p>x</p></div></div>'
            .'<div data-component="button" data-label="x" data-url="javascript:alert(1)"></div>'
            .'<div data-component="callout" data-icon="fr-icon-inexistante"><div data-slot="body"></div><div data-slot="pirate"></div></div>'
            .'<div data-component="tile" data-title="t" data-pictogram="../../secret"></div>'
            .'<div data-component="image" data-media-id="999999"></div>'
            .'<div data-component="download" data-media-ids="'.$image->getId().'"></div>'
            .'<div data-component="accordion"><div data-slot="title">Q <div data-component="badge" data-label="B"></div></div><div data-slot="body"><p>R</p></div></div>'
            .'<div data-component="table" data-caption="T"><div data-slot="body"><table><tbody><tr><td><div data-component="badge" data-label="B"></div></td></tr></tbody></table></div></div>'
            .'<table><tbody><tr><td>Hors composant</td></tr></tbody></table>'
            .'<div data-component="download" data-media-ids="'.$image->getId().'" data-media-ids-settings="{&quot;999&quot;:{&quot;surface&quot;:&quot;grey&quot;}}"></div>'
            .'<div data-component="columns" data-columns="6-6"><div data-slot="column">'
            .'<div data-component="columns" data-columns="6-6"><div data-slot="column"></div><div data-slot="column"></div></div></div></div>',
        );

        self::assertSame([
            'Composant inconnu : « inconnu ».',
            '« Alerte » : valeur non autorisée pour « Type ».',
            '« Bouton » : valeur non autorisée pour « Lien ».',
            '« Mise en avant » : valeur non autorisée pour « Icône ».',
            '« Mise en avant » : zone inconnue « pirate ».',
            '« Tuile » : valeur non autorisée pour « Pictogramme ».',
            '« Badge » ne peut pas être placé dans un titre ni dans une cellule de tableau.',
            'Un tableau se place dans le composant « Tableau ».',
            '« Documents à télécharger » : réglages par média non valides pour « Documents ».',
            'Le bloc « Colonnes » doit contenir 2 colonnes, il en contient 1.',
            '« Image » : le média n° 999999 n\'existe pas, retirez-le du composant.',
            '« Documents à télécharger » : le média n° '.$image->getId().' n\'est pas un document.',
        ], $errors);
    }

    public function testEditorFieldShowsTheErrorsAndKeepsNothing(): void
    {
        $form = static::getContainer()->get('form.factory')->createNamedBuilder('page', options: ['csrf_protection' => false])
            ->add('content', EditorType::class)->getForm();
        $form->submit(['content' => '<div data-component="columns" data-columns="4-8"><div data-slot="column"><p>Seule</p></div></div>']);

        self::assertFalse($form->isValid());
        self::assertSame('Le bloc « Colonnes » doit contenir 2 colonnes, il en contient 1.', $form->get('content')->getErrors()[0]->getMessage());
    }

    private function validator(): ContentValidator
    {
        return static::getContainer()->get('ckeditor_dsfr.content_validator');
    }
}
