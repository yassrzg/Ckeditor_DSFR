<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Tests\Unit;

use Yassrzg\CkeditorDsfrBundle\Editor\PlainText;
use Yassrzg\CkeditorDsfrBundle\EventListener\VideoFrameSourceListener;
use Yassrzg\CkeditorDsfrBundle\Media\MediaDirectories;
use Yassrzg\CkeditorDsfrBundle\Media\MediaExtensions;
use Yassrzg\CkeditorDsfrBundle\Settings\EditorSettings;
use PHPUnit\Framework\TestCase;

/**
 * Valeurs saisies par l'administrateur ou le rédacteur : hôtes vidéo, formats, dossiers, texte simple, politique CSP.
 */
final class ValuesTest extends TestCase
{
    public function testVideoHostsAreParsed(): void
    {
        self::assertSame(
            ['hosts' => ['www.youtube.com', 'player.vimeo.com'], 'invalid' => ['pas_un_hôte']],
            EditorSettings::parseHosts("www.youtube.com, https://player.vimeo.com/video/1\nWWW.YOUTUBE.COM; pas_un_hôte"),
        );
    }

    public function testDangerousExtensionsAreAlwaysForbidden(): void
    {
        self::assertSame(['pdf', 'docx'], MediaExtensions::normalize(['.PDF', ' docx ', 'pdf', '', '../x']));
        self::assertSame(['php', 'svg'], MediaExtensions::forbidden(['pdf', 'PHP', '.svg']));
        self::assertSame([], array_intersect([...MediaExtensions::IMAGE_CATALOG, ...MediaExtensions::DOCUMENT_CATALOG], MediaExtensions::FORBIDDEN), 'Catalogue par défaut sans format dangereux.');
    }

    public function testDirectoriesStayUnderTheRoot(): void
    {
        self::assertSame('contenu/images', MediaDirectories::normalize('/contenu/images/'));
        self::assertSame('contenu/images', MediaDirectories::normalize('contenu\\images'));
        foreach (['../etc', 'a/../b', '.cache', '', 'a//b', 'a b', '/', 'a/b/c/d/e/f'] as $invalid) {
            self::assertNull(MediaDirectories::normalize($invalid), $invalid);
        }
    }

    public function testPlainTextIsConvertedToParagraphs(): void
    {
        self::assertTrue(PlainText::isPlainText("Texte\nsimple"));
        self::assertFalse(PlainText::isPlainText('<p>HTML</p>'));
        self::assertFalse(PlainText::isPlainText('l&#039;équipe'), 'Entité : HTML enregistré par l\'éditeur.');
        self::assertSame('<p>A &amp; B<br>C</p><p>D</p>', PlainText::toHtml("A & B\nC\n\nD"));
        self::assertSame('', PlainText::toHtml('  '));
    }

    public function testVideoHostsAreAddedToTheFrameSources(): void
    {
        self::assertSame("default-src 'self'; frame-src 'self' https://a.fr", VideoFrameSourceListener::withFrameSources("default-src 'self'", ['a.fr']));
        self::assertSame("frame-src 'self' https://a.fr", VideoFrameSourceListener::withFrameSources("frame-src 'none'", ['a.fr']));
        self::assertSame("frame-src 'self' https://x.fr https://a.fr", VideoFrameSourceListener::withFrameSources("frame-src 'self' https://x.fr;", ['a.fr']));
    }
}
