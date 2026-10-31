<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Tests\Functional;

use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Yassrzg\CkeditorDsfrBundle\Media\MediaException;
use Yassrzg\CkeditorDsfrBundle\Media\MediaManager;
use Yassrzg\CkeditorDsfrBundle\Media\MediaStorage;
use Yassrzg\CkeditorDsfrBundle\Tests\App\Entity\Page;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MediaLibraryTest extends FunctionalTestCase
{
    public function testImageIsAddedWithAlternativeTextAndServed(): void
    {
        $this->login('admin');
        $crawler = $this->client->request('GET', $this->url('ckeditor_dsfr_media_create', ['type' => 'image']));
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['media_form[title]'] = 'Schéma réseau';
        $form['media_form[file]']->upload(self::FIXTURES.'image.png');
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422, 'Texte alternatif obligatoire (RGAA).');
        self::assertSelectorTextContains('main', 'Le texte alternatif est obligatoire');

        $form = $this->client->getCrawler()->selectButton('Enregistrer')->form();
        $form['media_form[title]'] = 'Schéma réseau';
        $form['media_form[alt]'] = 'Schéma du réseau';
        $form['media_form[file]']->upload(self::FIXTURES.'image.png');
        $this->client->submit($form);
        self::assertResponseRedirects($this->url('ckeditor_dsfr_media_index', ['type' => 'image']));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.fr-alert--success', 'Le média a été ajouté.');

        $media = $this->em->getRepository(Media::class)->findOneBy(['title' => 'Schéma réseau']);
        self::assertNotNull($media);
        self::assertSame('PNG', $media->getFormat());
        self::assertNotNull($media->getWidth(), 'Dimensions lues à l\'envoi.');
        $path = static::getContainer()->get(MediaStorage::class)->path($media);
        self::assertFileExists($path);
        self::assertStringContainsString('/var/media/image/', $path, 'Dossier par défaut des images.');

        $crawler = $this->client->request('GET', $this->url('ckeditor_dsfr_media_index'));
        self::assertSame('Schéma réseau', $crawler->filter('.cmp-media-card .fr-card__title a')->text());
        self::assertCount(0, $crawler->filter('main table'), 'Cartes, sans tableau.');

        $this->client->request('GET', $this->url('ckeditor_dsfr_media_selection', ['type' => 'image']));
        self::assertSame('Schéma réseau', json_decode((string) $this->client->getResponse()->getContent(), true)[0]['title']);

        // Fichier servi aux utilisateurs autorisés (roles.view), la médiathèque réservée aux administrateurs (roles.admin).
        $this->login('reader');
        $this->client->request('GET', $this->url('ckeditor_dsfr_media_show', ['id' => $media->getId()]));
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('inline', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $this->client->getResponse()->headers->get('X-Content-Type-Options'));
        $this->client->request('GET', $this->url('ckeditor_dsfr_media_index'));
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', $this->url('ckeditor_dsfr_media_selection', ['type' => 'image']));
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnonymousVisitorCannotDownloadWhenViewIsRestricted(): void
    {
        $media = $this->createMedia(MediaType::DOCUMENT, 'Guide', 'document.pdf');
        $this->client->request('GET', $this->url('ckeditor_dsfr_media_download', ['id' => $media->getId()]));
        self::assertResponseStatusCodeSame(401, 'roles.view = IS_AUTHENTICATED dans le noyau de test.');
    }

    public function testWrongFormatIsRefused(): void
    {
        $this->login('admin');
        $crawler = $this->client->request('GET', $this->url('ckeditor_dsfr_media_create', ['type' => 'image']));
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['media_form[title]'] = 'Pas une image';
        $form['media_form[decorative]']->tick();
        $form['media_form[file]']->upload(self::FIXTURES.'document.pdf');
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Format non accepté');
        self::assertSame(0, $this->em->getRepository(Media::class)->count([]));
    }

    public function testDocumentIsDownloadedWithFormatAndSize(): void
    {
        $media = $this->createMedia(MediaType::DOCUMENT, 'Guide des réquisitions', 'document.pdf');
        self::assertMatchesRegularExpression('/^PDF – \d+ (o|Ko)$/', $media->getDetail());

        $this->login('reader');
        $this->client->request('GET', $this->url('ckeditor_dsfr_media_download', ['id' => $media->getId()]));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('.pdf', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testMediaUsedByAContentCannotBeDeleted(): void
    {
        $media = $this->createMedia(MediaType::DOCUMENT, 'Guide', 'document.pdf');
        $other = $this->createMedia(MediaType::IMAGE, 'Vignette', 'image.png');
        $path = static::getContainer()->get(MediaStorage::class)->path($media);
        $page = new Page('Procédure', '<div data-component="download" data-media-ids="99,'.$media->getId().'" data-variant="link"></div>');
        $thumbnailPage = new Page('Accueil');
        $thumbnailPage->thumbnail = $other;
        $columnsPage = new Page('Mise en page', '<div data-component="columns" data-columns="4-8"><div data-slot="column"><div data-component="image" data-media-id="'.$other->getId().'"></div></div><div data-slot="column"><p>Texte</p></div></div>');
        $this->em->persist($page);
        $this->em->persist($thumbnailPage);
        $this->em->persist($columnsPage);
        $this->em->flush();

        $this->login('admin');
        $crawler = $this->client->request('GET', $this->url('ckeditor_dsfr_media_edit', ['id' => $media->getId()]));
        self::assertStringContainsString('Procédure', $crawler->filter('main')->text());
        self::assertCount(0, $crawler->filter('form[action="'.$this->url('ckeditor_dsfr_media_delete', ['id' => $media->getId()]).'"]'), 'Pas de bouton de suppression.');
        $crawler = $this->client->request('GET', $this->url('ckeditor_dsfr_media_edit', ['id' => $other->getId()]));
        self::assertStringContainsString('Accueil', $crawler->filter('main')->text(), 'Relation déclarée (vignette).');
        self::assertStringContainsString('Mise en page', $crawler->filter('main')->text(), 'Média placé dans une colonne.');

        // Règle appliquée par le service, pas seulement par l'interface.
        try {
            static::getContainer()->get(MediaManager::class)->delete($this->em->find(Media::class, $media->getId()));
            self::fail('Suppression d\'un média utilisé acceptée.');
        } catch (MediaException $e) {
            self::assertStringContainsString('« Procédure »', $e->getMessage());
        }
        self::assertFileExists($path);

        $page = $this->em->find(Page::class, $page->id);
        $page->content = '<p>Plus de document.</p>';
        $this->em->flush();
        $crawler = $this->client->request('GET', $this->url('ckeditor_dsfr_media_edit', ['id' => $media->getId()]));
        $this->client->submit($crawler->filter('form[action="'.$this->url('ckeditor_dsfr_media_delete', ['id' => $media->getId()]).'"]')->form());
        self::assertResponseRedirects();
        $this->em->clear();
        self::assertNull($this->em->getRepository(Media::class)->find($media->getId()));
        self::assertFileDoesNotExist($path);
    }

    public function testDeleteRequiresCsrfToken(): void
    {
        $media = $this->createMedia(MediaType::DOCUMENT, 'Guide', 'document.pdf');
        $this->login('admin');
        $this->client->request('POST', $this->url('ckeditor_dsfr_media_delete', ['id' => $media->getId()]), ['_token' => 'faux']);
        self::assertResponseRedirects($this->url('ckeditor_dsfr_media_edit', ['id' => $media->getId()]));
        $this->em->clear();
        self::assertNotNull($this->em->find(Media::class, $media->getId()));
    }

    public function testZipDocumentAddedFromTheEditor(): void
    {
        $this->login('admin');
        $crawler = $this->client->request('GET', $this->url('ckeditor_dsfr_media_quick_add', ['type' => 'document']));
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('form'), 'Champs seuls : la fenêtre est dans le formulaire de la page.');
        $token = $crawler->filter('input[name="media_form[_token]"]')->attr('value');

        $this->client->request('POST', $this->url('ckeditor_dsfr_media_quick_add', ['type' => 'document']), ['media_form' => ['title' => 'Annexe', '_token' => $token]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.fr-error-text');

        $zip = sys_get_temp_dir().'/'.uniqid('zip_').'.zip';
        $archive = new \ZipArchive();
        $archive->open($zip, \ZipArchive::CREATE);
        $archive->addFromString('lisezmoi.txt', 'Annexe');
        $archive->close();
        $this->client->request('POST', $this->url('ckeditor_dsfr_media_quick_add', ['type' => 'document']), ['media_form' => ['title' => 'Annexe', '_token' => $token]], ['media_form' => ['file' => new UploadedFile($zip, 'annexe.zip', 'application/zip', null, true)]]);
        self::assertResponseStatusCodeSame(201);
        $media = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Annexe', $media['title']);
        self::assertStringStartsWith('ZIP', $media['detail'], 'Format affiché.');

        $this->login('reader');
        $this->client->request('GET', $this->url('ckeditor_dsfr_media_quick_add', ['type' => 'image']));
        self::assertResponseStatusCodeSame(403);
    }

    public function testDsfrIconsAndPictogramsAreListedForTheEditor(): void
    {
        $this->login('admin');
        $this->client->request('GET', $this->url('ckeditor_dsfr_artwork'));
        self::assertResponseIsSuccessful();
        $artwork = json_decode((string) $this->client->getResponse()->getContent(), true);
        $icons = array_column($artwork['icon'], 'id');
        self::assertContains('fr-icon-warning-line', $icons, 'Icône propre au DSFR (fichier fr--warning-line.svg).');
        self::assertContains('fr-icon-information-line', $icons);
        self::assertContains('digital/search', array_column($artwork['pictogram'], 'id'));

        $this->login('reader');
        $this->client->request('GET', $this->url('ckeditor_dsfr_artwork'));
        self::assertResponseStatusCodeSame(403);
    }
}
