<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Tests\Unit;

use Yassrzg\CkeditorDsfrBundle\Editor\ComponentField;
use PHPUnit\Framework\TestCase;

/**
 * Réglages conditionnels : transmis à l'éditeur avec les attributs data-* des réglages dont ils dépendent.
 */
final class ComponentFieldConditionsTest extends TestCase
{
    public function testConditionsAreSentWithAttributes(): void
    {
        $field = ComponentField::choice('size', 'Taille', ['md' => 'Moyenne', 'lg' => 'Grande'])
            ->when('variant', ['card', 'tile'])
            ->optionWhen('lg', ['field' => 'variant', 'values' => ['card']]);

        $editor = $field->toArray();
        self::assertSame(['field' => ['data-variant'], 'values' => ['card', 'tile']], $editor['showIf']);
        self::assertSame(['lg' => ['field' => ['data-variant'], 'values' => ['card']]], $editor['optionShowIf']);

        $icon = ComponentField::icon('icon', 'Icône')->whenFilled('detail');
        self::assertSame(['field' => ['data-detail'], 'filled' => true], $icon->toArray()['showIf']);
        $picto = ComponentField::pictogram('pictogram', 'Pictogramme')->whenFilled('media-id', false);
        self::assertSame(['field' => ['data-media-id'], 'filled' => false], $picto->toArray()['showIf']);

        self::assertArrayNotHasKey('showIf', ComponentField::text('title', 'Titre')->toArray(), 'Sans condition : toujours proposé.');
    }
}
