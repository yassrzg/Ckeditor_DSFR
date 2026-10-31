<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Tests\App;

use Yassrzg\CkeditorDsfrBundle\CkeditorDsfrBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\StimulusBundle\StimulusBundle;

/**
 * Projet minimal pour les tests du bundle : SQLite, utilisateurs en mémoire, gabarit de base et entité « Page »
 * dont le contenu et la vignette utilisent les médias.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new DoctrineBundle();
        yield new DoctrineMigrationsBundle();
        yield new StimulusBundle();
        yield new CkeditorDsfrBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return \dirname(__DIR__, 2).'/var/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return \dirname(__DIR__, 2).'/var/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => true,
            'form' => true,
            'asset_mapper' => ['paths' => []],
            'property_access' => true,
            'router' => ['utf8' => true],
        ]);
        // Journaux des tests : erreurs seulement (pas de sortie de débogage).
        $container->services()->set('logger', \Psr\Log\NullLogger::class);
        // Service non utilisé par le bundle lui-même (destiné au projet) : gardé pour les tests.
        $container->services()->alias('test.content_text', 'ckeditor_dsfr.content_text')->public();
        $container->extension('security', [
            'password_hashers' => ['Symfony\Component\Security\Core\User\InMemoryUser' => 'plaintext'],
            'providers' => ['users' => ['memory' => ['users' => [
                'admin' => ['password' => 'admin', 'roles' => ['ROLE_ADMIN']],
                'superadmin' => ['password' => 'super', 'roles' => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']],
                'reader' => ['password' => 'reader', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'users', 'http_basic' => null]],
        ]);
        $container->extension('twig', ['default_path' => __DIR__.'/templates']);
        $container->extension('doctrine', [
            // Autre base possible (ex. PostgreSQL) : variable d'environnement CKEDITOR_DSFR_TEST_DATABASE_URL.
            'dbal' => ['url' => $_SERVER['CKEDITOR_DSFR_TEST_DATABASE_URL'] ?? 'sqlite:///'.\dirname(__DIR__, 2).'/var/test.db'],
            'orm' => [
                'mappings' => ['TestApp' => ['type' => 'attribute', 'is_bundle' => false, 'dir' => __DIR__.'/Entity', 'prefix' => 'Yassrzg\CkeditorDsfrBundle\Tests\App\Entity']],
            ],
        ]);
        $container->extension('doctrine_migrations', ['migrations_paths' => []]);
        $container->extension('ckeditor_dsfr', [
            'dsfr' => ['directory' => \dirname(__DIR__).'/Fixtures/dsfr'],
            'storage_root' => \dirname(__DIR__, 2).'/var/media',
            'roles' => ['admin' => 'ROLE_ADMIN', 'settings' => 'ROLE_SUPER_ADMIN', 'view' => 'IS_AUTHENTICATED'],
            'media_usages' => [[
                'class' => 'Yassrzg\CkeditorDsfrBundle\Tests\App\Entity\Page',
                'label' => 'title',
                'content_fields' => ['content'],
                'relation_fields' => ['thumbnail'],
            ]],
            'content_security_policy' => true,
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@CkeditorDsfrBundle/config/routes/admin.php')->prefix('/admin');
        $routes->import('@CkeditorDsfrBundle/config/routes/media.php');
    }
}
