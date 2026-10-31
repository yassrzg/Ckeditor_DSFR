<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Command;

use Yassrzg\CkeditorDsfrBundle\CkeditorDsfrBundle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Installation dans le projet (AssetMapper / Stimulus), sans réseau :
 * - importmap.php : CKEditor 5 et SortableJS fournis par le bundle, seulement s'ils ne sont pas déjà déclarés ;
 * - assets/controllers.json : contrôleur Stimulus « ckeditor-dsfr » (chargé à la demande).
 * Relancer la commande ne modifie rien de ce qui est déjà installé.
 */
#[AsCommand(name: 'ckeditor-dsfr:install', description: 'Déclare CKEditor 5, SortableJS et le contrôleur Stimulus du bundle CKEditor DSFR')]
final class InstallCommand extends Command
{
    private const string PACKAGE = '@yassrzg/ckeditor-dsfr-bundle';

    /** Modules JavaScript utilisés par le contrôleur : nom d'import => fichier fourni par le bundle. */
    private const array MODULES = [
        'ckeditor5' => 'vendor/ckeditor5/ckeditor5.js',
        'ckeditor5/translations/fr.js' => 'vendor/ckeditor5/translations/fr.js',
        'sortablejs' => 'vendor/sortablejs/sortable.js',
    ];

    public function __construct(
        private readonly string $projectDir,
        private readonly string $dsfrDirectory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les modifications sans les écrire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $io->title('Installation du bundle CKEditor DSFR');

        $importmap = $this->projectDir.'/importmap.php';
        if (!is_file($importmap)) {
            $io->error('importmap.php introuvable : le projet doit utiliser AssetMapper (composer require symfony/asset-mapper).');

            return Command::FAILURE;
        }
        $entries = require $importmap;
        if (!\is_array($entries)) {
            $io->error('importmap.php ne renvoie pas de tableau.');

            return Command::FAILURE;
        }
        if (!isset($entries['@hotwired/stimulus'])) {
            $io->warning('Stimulus n\'est pas déclaré dans importmap.php : installez symfony/stimulus-bundle.');
        }

        $added = '';
        foreach (self::MODULES as $name => $file) {
            if (isset($entries[$name])) {
                $io->writeln(\sprintf(' <comment>déjà installé</comment>  %s', $name));
                continue;
            }
            $added .= \sprintf("    %s => [\n        'path' => %s,\n    ],\n", var_export($name, true), var_export(CkeditorDsfrBundle::ASSET_NAMESPACE.'/'.$file, true));
            $io->writeln(\sprintf(' <info>ajouté</info>          %s (fourni par le bundle)', $name));
        }
        if ('' !== $added && !$dryRun) {
            $content = (string) file_get_contents($importmap);
            $end = strrpos($content, '];');
            if (false === $end) {
                $io->error('Fin du tableau de importmap.php introuvable : ajoutez les entrées à la main (voir docs/installation.md).');

                return Command::FAILURE;
            }
            $comment = "    // Fournis par le bundle CKEditor DSFR (commande ckeditor-dsfr:install) ; CKEditor 5 sous licence GPL.\n";
            (new Filesystem())->dumpFile($importmap, substr($content, 0, $end).$comment.$added.substr($content, $end));
        }

        $controllersJson = $this->projectDir.'/assets/controllers.json';
        $controllers = is_file($controllersJson) ? json_decode((string) file_get_contents($controllersJson), true, 512, \JSON_THROW_ON_ERROR) : ['controllers' => [], 'entrypoints' => []];
        if (isset($controllers['controllers'][self::PACKAGE]['editor'])) {
            $io->writeln(' <comment>déjà installé</comment>  contrôleur Stimulus ckeditor-dsfr');
        } else {
            $controllers['controllers'][self::PACKAGE] = ['editor' => ['enabled' => true, 'fetch' => 'lazy']];
            if (!$dryRun) {
                (new Filesystem())->dumpFile($controllersJson, json_encode($controllers, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n");
            }
            $io->writeln(' <info>ajouté</info>          contrôleur Stimulus ckeditor-dsfr (assets/controllers.json)');
        }

        if (!is_file($this->dsfrDirectory.'/utility/icons/icons.min.css')) {
            $io->warning(\sprintf('DSFR introuvable dans %s : réglez ckeditor_dsfr.dsfr.directory (icônes et pictogrammes de l\'éditeur).', $this->dsfrDirectory));
        }

        $io->success($dryRun ? 'Simulation terminée : rien n\'a été écrit.' : 'Installation terminée.');
        $io->listing([
            'Routes : importez @CkeditorDsfrBundle/config/routes/admin.php et media.php (docs/installation.md).',
            'Tables : php bin/console doctrine:migrations:migrate.',
            'Styles des composants : {{ ckeditor_dsfr_stylesheets() }} dans le <head> du gabarit de base.',
            'Formulaires : champ Yassrzg\CkeditorDsfrBundle\Form\EditorType ; affichage : filtre ckeditor_dsfr_render.',
        ]);

        return Command::SUCCESS;
    }
}
