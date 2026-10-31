<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\EventListener;

use Yassrzg\CkeditorDsfrBundle\Settings\EditorSettings;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * ckeditor_dsfr.content_security_policy : les hôtes vidéo autorisés sont ajoutés à la directive frame-src de l'en-tête
 * Content-Security-Policy posé par le projet (après lui : priorité basse). Sans en-tête CSP, rien n'est ajouté.
 */
final readonly class VideoFrameSourceListener
{
    public function __construct(
        private EditorSettings $settings,
        private bool $enabled,
    ) {
    }
    public function onKernelResponse(ResponseEvent $event): void
    {
        $headers = $event->getResponse()->headers;
        if (!$this->enabled || !$event->isMainRequest() || !$headers->has('Content-Security-Policy')) {
            return;
        }
        $hosts = $this->settings->videoHosts();
        if ([] === $hosts) {
            return;
        }

        $headers->set('Content-Security-Policy', self::withFrameSources((string) $headers->get('Content-Security-Policy'), $hosts));
    }

    /**
     * @param list<string> $hosts
     */
    public static function withFrameSources(string $policy, array $hosts): string
    {
        $sources = implode(' ', array_map(static fn (string $host): string => 'https://'.$host, $hosts));
        $directives = array_values(array_filter(array_map('trim', explode(';', $policy))));
        foreach ($directives as $i => $directive) {
            if (preg_match('/^frame-src\s/i', $directive.' ')) {
                $directives[$i] = str_contains($directive, "'none'") ? "frame-src 'self' ".$sources : $directive.' '.$sources;

                return implode('; ', $directives);
            }
        }
        $directives[] = "frame-src 'self' ".$sources;

        return implode('; ', $directives);
    }
}
