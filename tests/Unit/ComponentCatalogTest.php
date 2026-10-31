<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yassrzg\CkeditorDsfrBundle\Editor\ComponentCatalog;

/**
 * Catalogue YAML : fichier du bundle, fichiers du projet (ajout, remplacement, retrait) et erreurs de déclaration.
 */
final class ComponentCatalogTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map(unlink(...), $this->files);
    }

    public function testBundleCatalogDeclaresEveryComponent(): void
    {
        $catalog = ComponentCatalog::load([ComponentCatalog::FILE]);

        foreach (['image', 'columns', 'download', 'card', 'tile', 'button', 'modal-button', 'accordion', 'tab', 'table', 'callout', 'highlight', 'alert', 'notice', 'badge', 'tag', 'quote', 'video', 'media-text', 'icon', 'pictogram'] as $name) {
            self::assertArrayHasKey($name, $catalog);
            self::assertFileExists(__DIR__.'/../../templates/components/'.$name.'.html.twig', 'Gabarit du composant '.$name);
        }
        foreach (array_filter(array_column($catalog, 'group')) as $group) {
            self::assertFileExists(__DIR__.'/../../templates/groups/'.$group.'.html.twig');
        }
        $notice = array_column($catalog['notice']['fields'], null, 'name');
        self::assertSame(['no' => ['field' => 'type', 'values' => ['info', 'warning', 'alert']]], $notice['icon']['optionShowIf']);
        self::assertSame(['yes' => 'Icône du type', 'no' => 'Sans icône'], $notice['icon']['options'], 'Valeurs « yes » / « no » gardées en texte.');
        $download = array_column($catalog['download']['fields'], null, 'name');
        self::assertSame(['pictogram', 'surface'], array_column($download['media-ids']['items'], 'name'));
        self::assertFalse($download['media-ids']['items'][1]['required'], 'Réglage par média facultatif.');
        self::assertSame(['field' => 'variant', 'values' => ['card', 'tile']], $catalog['download']['width']['when']);
        self::assertSame('16x9', array_column($catalog['image']['fields'], null, 'name')['ratio']['default'], 'Liste partagée « @ratios ».');
    }

    public function testProjectFilesAddReplaceAndRemoveComponents(): void
    {
        $catalog = ComponentCatalog::load([ComponentCatalog::FILE, $this->file(<<<'YAML'
            lists:
                services: {sdi: Service A, cyber: Service B}
            components:
                contact:
                    label: Contact
                    description: Carte de contact d'un service.
                    icon: fr-icon-mail-line
                    fields:
                        service: {type: choice, label: Service, options: '@services'}
                        email: {type: text, label: Adresse, when: {service: [cyber]}}
                quote:
                    label: Citation courte
                    description: Citation sans image.
                    icon: fr-icon-chat-quote-line
                    slots: {body: {label: Contenu}}
                video: {enabled: false}
            YAML)]);

        self::assertSame(['Service A', 'Service B'], array_values($catalog['contact']['fields'][0]['options']));
        self::assertSame(['field' => 'service', 'values' => ['cyber']], $catalog['contact']['fields'][1]['showIf']);
        self::assertSame('Citation courte', $catalog['quote']['label'], 'Composant du bundle remplacé.');
        self::assertSame([], $catalog['quote']['fields']);
        self::assertArrayNotHasKey('video', $catalog, 'Composant du bundle retiré.');
        self::assertArrayHasKey('image', $catalog);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidDeclarations(): iterable
    {
        yield 'type inconnu' => ['x: {label: X, description: D, icon: i, fields: {a: {type: couleur, label: A}}}', 'composant « x », réglage « a » : type parmi'];
        yield 'liste inconnue' => ['x: {label: X, description: D, icon: i, fields: {a: {type: choice, label: A, options: "@absente"}}}', 'liste « @absente » inconnue'];
        yield 'condition sur un réglage absent' => ['x: {label: X, description: D, icon: i, fields: {a: {type: text, label: A, when_filled: [b]}}}', 'condition sur un réglage inconnu (« b »)'];
        yield 'clé inconnue' => ['x: {label: X, description: D, icon: i, couleur: rouge}', 'clé inconnue « couleur »'];
        yield 'libellé manquant' => ['x: {description: D, icon: i}', '« label » obligatoire'];
        yield 'colonnes sans disposition' => ['x: {label: X, description: D, icon: i, layout: true, slots: {column: {}}}', 'un bloc de colonnes (layout)'];
        yield 'défaut hors liste' => ['x: {label: X, description: D, icon: i, fields: {a: {type: choice, label: A, options: {b: B}, default: c}}}', 'valeur par défaut absente de la liste'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidDeclarations')]
    public function testInvalidDeclarationIsRefusedWithAClearMessage(string $component, string $message): void
    {
        $file = $this->file("components:\n    ".$component."\n");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        ComponentCatalog::load([$file]);
    }

    private function file(string $yaml): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'catalog');
        file_put_contents($file, $yaml);
        $this->files[] = $file;

        return $file;
    }
}
