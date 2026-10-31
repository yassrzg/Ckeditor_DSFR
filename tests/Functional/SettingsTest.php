<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Tests\Functional;

use Yassrzg\CkeditorDsfrBundle\Editor\ComponentRegistry;
use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Yassrzg\CkeditorDsfrBundle\Form\EditorType;
use Yassrzg\CkeditorDsfrBundle\Media\MediaStorage;
use Yassrzg\CkeditorDsfrBundle\Settings\EditorSettings;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réglages de l'éditeur modifiés par l'administrateur : composants, vidéos, formats, poids et dossiers des médias.
 */
final class SettingsTest extends FunctionalTestCase
{
    public function testSettingsAreReservedToTheConfiguredRole(): void
    {
        $this->login('admin');
        $this->client->request('GET', $this->url('ckeditor_dsfr_settings'));
        self::assertResponseStatusCodeSame(403, 'roles.settings = ROLE_SUPER_ADMIN dans le noyau de test.');
        $crawler = $this->client->request('GET', $this->url('ckeditor_dsfr_media_index'));
        self::assertCount(0, $crawler->filter('a[href="'.$this->url('ckeditor_dsfr_settings').'"]'), 'Lien masqué sans le droit.');

        $this->login('superadmin');
        $crawler = $this->client->request('GET', $this->url('ckeditor_dsfr_settings'));
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('textarea#ckeditor_dsfr_settings_video_hosts'));
        self::assertSame('image', $crawler->filter('#ckeditor_dsfr_settings_image_directory')->attr('value'), 'Valeurs par défaut de la configuration.');
        self::assertSame(['jpg', 'jpeg', 'png', 'webp'], $crawler->filter('input[name="ckeditor_dsfr_settings[image_extensions][]"]:checked')->each(static fn (Crawler $c): string => (string) $c->attr('value')));
        self::assertCount(0, $crawler->filter('input[value="svg"], input[value="php"], input[value="html"]'), 'Formats dangereux jamais proposés.');
    }

    public function testVideoHostsFormatsAndComponentsAreSaved(): void
    {
        $this->login('superadmin');
        $this->submit(['video_hosts' => "www.youtube.com\npas un hôte!"]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Hôte non valide : pas, un, hôte!');

        $this->submit([
            'video_hosts' => "www.youtube.com\nhttps://player.vimeo.com/video/1",
            'image_extensions' => ['png'],
            'image_max_size' => '2',
            'enabled_components' => ['image', 'card', 'video'],
        ]);
        self::assertResponseRedirects($this->url('ckeditor_dsfr_settings'));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.fr-alert--success', 'Les réglages de l\'éditeur ont été enregistrés.');

        $settings = static::getContainer()->get(EditorSettings::class);
        self::assertSame(['www.youtube.com', 'player.vimeo.com'], $settings->videoHosts(), 'Adresse complète réduite à son hôte.');
        self::assertSame(['png'], $settings->extensions(MediaType::IMAGE));
        self::assertSame(2, $settings->maxSize(MediaType::IMAGE));
        self::assertSame(['image', 'card', 'video'], array_keys(static::getContainer()->get(ComponentRegistry::class)->all()), 'Composants proposés dans l\'éditeur.');

        // Format retiré : refusé à l'envoi, aide du formulaire à jour.
        $this->login('admin');
        $crawler = $this->client->request('GET', $this->url('ckeditor_dsfr_media_create', ['type' => 'image']));
        self::assertStringContainsString('Formats acceptés : PNG. Poids maximal : 2 Mo.', $crawler->filter('main')->text());
        self::assertSame('.png', $crawler->filter('input[type=file]')->attr('accept'));

        // Hôtes vidéo ajoutés à la politique de sécurité du projet (content_security_policy: true).
        $policy = \Yassrzg\CkeditorDsfrBundle\EventListener\VideoFrameSourceListener::withFrameSources("default-src 'self'; frame-src 'self'", $settings->videoHosts());
        self::assertSame("default-src 'self'; frame-src 'self' https://www.youtube.com https://player.vimeo.com", $policy);
    }

    public function testVideoIsOfferedForTheAllowedSources(): void
    {
        $registry = static::getContainer()->get('ckeditor_dsfr.component_registry');
        $this->saveSettings(['video_sources' => ['link', 'upload'], 'video_hosts' => []]);
        self::assertArrayHasKey('video', $registry->all(), 'Fichier envoyé : proposé sans plateforme autorisée.');
        $source = array_column(array_column($registry->forEditor(), 'fields', 'name')['video'], null, 'name')['source'];
        self::assertSame(['upload'], $source['offered'], 'Lien sans plateforme : non proposé.');

        $this->saveSettings(['video_sources' => ['link'], 'video_hosts' => []]);
        self::assertArrayNotHasKey('video', $registry->all());
        $this->saveSettings(['video_sources' => [], 'video_hosts' => ['www.youtube.com']]);
        self::assertArrayNotHasKey('video', $registry->all(), 'Aucune source cochée.');
    }

    public function testEditorFieldOffersOnlyEnabledComponents(): void
    {
        $this->saveSettings(['video_hosts' => [], 'image_extensions' => ['png'], 'document_extensions' => ['pdf'], 'disabled_components' => ['tab', 'quote']]);
        $form = static::getContainer()->get('form.factory')->createNamedBuilder('page', options: ['csrf_protection' => false])
            ->add('content', EditorType::class, ['label' => 'Contenu', 'data' => "Ancien texte\n\nsimple"])->getForm();
        $html = static::getContainer()->get('twig')->createTemplate('{{ form_widget(form) }}')->render(['form' => $form->createView()]);
        $page = new Crawler($html);

        $editor = $page->filter('[data-controller="ckeditor-dsfr"]');
        self::assertCount(1, $editor);
        // L'éditeur reçoit tous les composants (un composant retiré déjà présent reste lu et réenregistré) ; seuls les
        // composants proposés sont insérables et figurent dans la liste du choix.
        $insertable = array_column(json_decode((string) $editor->attr('data-ckeditor-dsfr-components-value'), true), 'insertable', 'name');
        self::assertFalse($insertable['tab']);
        self::assertFalse($insertable['quote']);
        self::assertFalse($insertable['video'], 'Aucun hôte vidéo : composant non proposé.');
        self::assertFalse($insertable['media-text'], 'Ancien composant « Image et texte » : remplacé par « Colonnes ».');
        self::assertTrue($insertable['card']);
        self::assertTrue($insertable['columns']);
        $choices = $page->filter('.cmp-chooser button')->each(static fn (Crawler $b): string => (string) $b->attr('data-ckeditor-dsfr-name-param'));
        self::assertSame(array_keys(array_filter($insertable)), $choices);
        self::assertCount(1, $page->filter('[data-ckeditor-dsfr-target="panel"][data-component-name="tab"]'), 'Réglages d\'un composant retiré toujours modifiables.');
        self::assertCount(1, $page->filter('[data-ckeditor-dsfr-target="notices"]'));
        self::assertSame('<p>Ancien texte</p><p>simple</p>', $page->filter('textarea')->text(), 'Ancienne valeur en texte simple convertie.');
        self::assertStringContainsString('/admin/mediatheque/selection/__TYPE__', (string) $editor->attr('data-ckeditor-dsfr-media-url-value'));

        $form->submit(['content' => '<p onclick="x()">Texte</p><script>alert(1)</script>']);
        self::assertSame('<p>Texte</p>', $form->get('content')->getData(), 'Saisie filtrée à l\'enregistrement.');
    }

    public function testStorageDirectoryChangeMovesExistingFiles(): void
    {
        $image = $this->createMedia(MediaType::IMAGE, 'Schéma', 'image.png', 'Schéma');
        $document = $this->createMedia(MediaType::DOCUMENT, 'Guide', 'document.pdf');
        $storage = static::getContainer()->get(MediaStorage::class);
        $oldImage = $storage->path($image);

        $this->login('superadmin');
        $this->submit(['image_directory' => '../../etc']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Dossier non valide');
        $this->submit(['image_directory' => 'commun', 'document_directory' => 'commun']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Les images, les documents et les vidéos doivent être dans des dossiers différents.');

        $this->submit(['image_directory' => 'contenu/images']);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.fr-alert--success', '1 fichier déplacé vers « contenu/images ».');

        $image = $this->em->find(Media::class, $image->getId());
        self::assertFileDoesNotExist($oldImage);
        self::assertStringEndsWith('/var/media/contenu/images/'.$image->getFilename(), $storage->path($image));
        self::assertFileExists($storage->path($image));
        self::assertFileExists($storage->path($this->em->find(Media::class, $document->getId())), 'Documents inchangés.');

        $this->login('reader');
        $this->client->request('GET', $this->url('ckeditor_dsfr_media_show', ['id' => $image->getId()]));
        self::assertResponseIsSuccessful('Fichier toujours servi après le déplacement.');
    }

    /**
     * Formulaire des réglages envoyé avec les valeurs actuelles, sauf celles données.
     *
     * @param array<string, mixed> $values
     */
    private function submit(array $values): Response
    {
        $crawler = $this->client->request('GET', $this->url('ckeditor_dsfr_settings'));
        $form = $crawler->selectButton('Enregistrer')->form();
        $data = $form->getPhpValues()['ckeditor_dsfr_settings'];
        foreach ($values as $name => $value) {
            $data[$name] = $value;
        }
        $this->client->request('POST', $this->url('ckeditor_dsfr_settings'), ['ckeditor_dsfr_settings' => $data]);

        return $this->client->getResponse();
    }
}
