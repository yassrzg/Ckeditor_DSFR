<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Controller;

use Yassrzg\CkeditorDsfrBundle\Editor\ComponentRegistry;
use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Yassrzg\CkeditorDsfrBundle\Form\SettingsFormType;
use Yassrzg\CkeditorDsfrBundle\Media\MediaException;
use Yassrzg\CkeditorDsfrBundle\Media\MediaRelocator;
use Yassrzg\CkeditorDsfrBundle\Settings\EditorSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réglages de l'éditeur (ckeditor_dsfr.roles.settings) : composants, vidéos, formats, poids et dossiers des médias.
 */
final class SettingsController extends AbstractEditorController
{
    public function __construct(
        private readonly EditorSettings $settings,
        private readonly ComponentRegistry $registry,
        private readonly MediaRelocator $relocator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->denyUnless('settings');
        $form = $this->createForm(SettingsFormType::class, SettingsFormType::dataFrom($this->settings, $this->registry));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $values = SettingsFormType::toSettings($form->getData(), $this->registry);
            try {
                $moved = $this->relocate($values);
                $this->settings->save($values);
                $this->registry->reset();
                $this->success('Les réglages de l\'éditeur ont été enregistrés.'.implode('', array_map(
                    static fn (string $directory, int $count): string => \sprintf(' %d fichier%s déplacé%2$s vers « %s ».', $count, $count > 1 ? 's' : '', $directory),
                    array_keys($moved),
                    $moved,
                )));

                return $this->redirectToRoute('ckeditor_dsfr_settings');
            } catch (MediaException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->renderForm('@CkeditorDsfr/admin/settings.html.twig', $form);
    }

    /**
     * Fichiers déplacés dans les nouveaux dossiers, avant l'enregistrement des réglages : si une catégorie échoue,
     * celle déjà déplacée revient dans son dossier et l'ancien réglage reste en vigueur.
     *
     * @param array<string, mixed> $values réglages au format de EditorSettings::save() (<type>_directory par catégorie)
     *
     * @return array<string, int> nouveau dossier => nombre de fichiers déplacés
     *
     * @throws MediaException
     */
    private function relocate(array $values): array
    {
        $done = [];
        $moved = [];
        try {
            foreach (MediaType::cases() as $type) {
                $from = $this->settings->directory($type);
                $to = $values[$type->value.'_directory'];
                $count = $this->relocator->relocate($type, $from, $to);
                $done[] = [$type, $from, $to];
                if ($count > 0) {
                    $moved[$to] = $count;
                }
            }
        } catch (MediaException $e) {
            foreach ($done as [$type, $from, $to]) {
                try {
                    $this->relocator->relocate($type, $to, $from);
                } catch (MediaException $rollback) {
                    $this->logger->critical('Fichiers de la médiathèque non remis dans leur dossier.', ['type' => $type->value, 'exception' => $rollback]);
                }
            }
            throw $e;
        }

        return $moved;
    }
}
