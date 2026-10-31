<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Tests\Functional;

use Yassrzg\CkeditorDsfrBundle\Command\InstallCommand;
use Yassrzg\CkeditorDsfrBundle\Doctrine\BundleSchema;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Installation dans un projet : migration (tables manquantes seulement) et déclaration des fichiers JavaScript.
 */
final class InstallationTest extends FunctionalTestCase
{
    public function testMigrationCreatesOnlyMissingTables(): void
    {
        $connection = $this->em->getConnection();
        self::assertSame([], BundleSchema::createSql(), 'Tables présentes : rien à créer.');

        $connection->executeStatement('DROP TABLE ckeditor_dsfr_setting');
        $sql = BundleSchema::createSql();
        self::assertCount(1, $sql);
        self::assertStringContainsString('CREATE TABLE ckeditor_dsfr_setting', $sql[0], 'Nom de table configuré, autre table intacte.');

        foreach ($sql as $query) {
            $connection->executeStatement($query);
        }
        self::assertSame([], BundleSchema::createSql(), 'Idempotente.');
    }

    public function testInstallDeclaresMissingModulesOnly(): void
    {
        $project = sys_get_temp_dir().'/'.uniqid('ckeditor_dsfr_project_');
        $filesystem = new Filesystem();
        $filesystem->dumpFile($project.'/importmap.php', "<?php\n\nreturn [\n    '@hotwired/stimulus' => [\n        'version' => '3.2.2',\n    ],\n    'sortablejs' => [\n        'version' => '1.15.7',\n    ],\n];\n");
        $command = new InstallCommand($project, \dirname(__DIR__).'/Fixtures/dsfr');

        $tester = new CommandTester($command);
        self::assertSame(0, $tester->execute(['--dry-run' => true]));
        self::assertStringNotContainsString('ckeditor5', (string) file_get_contents($project.'/importmap.php'), 'Simulation : rien d\'écrit.');

        $tester->execute([]);
        $importmap = require $project.'/importmap.php';
        self::assertSame('@yassrzg/ckeditor-dsfr-bundle/vendor/ckeditor5/ckeditor5.js', $importmap['ckeditor5']['path']);
        self::assertSame('1.15.7', $importmap['sortablejs']['version'], 'Module déjà installé : conservé.');
        $controllers = json_decode((string) file_get_contents($project.'/assets/controllers.json'), true);
        self::assertSame(['enabled' => true, 'fetch' => 'lazy'], $controllers['controllers']['@yassrzg/ckeditor-dsfr-bundle']['editor']);

        $before = file_get_contents($project.'/importmap.php');
        $tester->execute([]);
        self::assertSame($before, file_get_contents($project.'/importmap.php'), 'Relancée : aucune modification.');
        $filesystem->remove($project);
    }
}
