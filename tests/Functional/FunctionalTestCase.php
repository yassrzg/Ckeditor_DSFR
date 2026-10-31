<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Tests\Functional;

use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Yassrzg\CkeditorDsfrBundle\Form\SettingsFormType;
use Yassrzg\CkeditorDsfrBundle\Media\MediaStorage;
use Yassrzg\CkeditorDsfrBundle\Settings\EditorSettings;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Base des tests fonctionnels : base SQLite recréée, dossier des médias vidé, connexion des utilisateurs du noyau de test.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected const string FIXTURES = __DIR__.'/../Fixtures/';

    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        (new Filesystem())->remove(static::getContainer()->getParameter('ckeditor_dsfr.storage_root'));
    }

    /**
     * Enregistre des réglages de l'éditeur : valeurs en vigueur, remplacées par $values (même format que EditorSettings::save()).
     *
     * @param array<string, mixed> $values
     */
    protected function saveSettings(array $values): void
    {
        $settings = static::getContainer()->get(EditorSettings::class);
        $registry = static::getContainer()->get('ckeditor_dsfr.component_registry');
        $settings->save([...SettingsFormType::toSettings(SettingsFormType::dataFrom($settings, $registry), $registry), ...$values]);
        $registry->reset();
    }

    /**
     * @param 'admin'|'superadmin'|'reader' $username
     */
    protected function login(string $username): void
    {
        $this->client->loginUser(static::getContainer()->get('security.user.provider.concrete.users')->loadUserByIdentifier($username));
    }

    protected function createMedia(MediaType $type, string $title, string $fixture, ?string $alt = null): Media
    {
        $copy = sys_get_temp_dir().'/'.uniqid('media_').'-'.$fixture;
        copy(self::FIXTURES.$fixture, $copy);
        $media = (new Media($type))->setTitle($title)->setAlt($alt)->setDecorative(null === $alt);
        static::getContainer()->get(MediaStorage::class)->store(new UploadedFile($copy, $fixture, null, null, true), $media);
        $this->em->persist($media);
        $this->em->flush();

        return $media;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function url(string $route, array $parameters = []): string
    {
        return static::getContainer()->get('router')->generate($route, $parameters);
    }
}
