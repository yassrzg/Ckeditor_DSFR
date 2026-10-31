<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Form;

use Yassrzg\CkeditorDsfrBundle\Editor\ContentSanitizer;
use Yassrzg\CkeditorDsfrBundle\Editor\ContentValidator;
use Yassrzg\CkeditorDsfrBundle\Editor\PlainText;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Éditeur à composants DSFR (CKEditor 5, contrôleur Stimulus ckeditor-dsfr) : zone de texte remplacée par l'éditeur.
 * Gabarit : @CkeditorDsfr/form/editor.html.twig ; affichage : filtre Twig ckeditor_dsfr_render.
 *
 * Valeur stockée → éditeur : une ancienne valeur en texte simple devient des paragraphes (retours à la ligne gardés).
 * Saisie → valeur stockée : HTML filtré par ContentSanitizer (null si vide), puis contrôlé par ContentValidator
 * (composants, valeurs, médias, colonnes) : une erreur est affichée sur le champ et rien n'est enregistré.
 *
 * @extends AbstractType<string|null>
 */
final class EditorType extends AbstractType
{
    public function __construct(
        private readonly ContentSanitizer $sanitizer,
        private readonly ContentValidator $validator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?string $value): string => PlainText::isPlainText($value) ? PlainText::toHtml($value) : (string) $value,
            fn (?string $html): ?string => $this->sanitizer->sanitize($html),
        ));
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $data = $event->getForm()->getData();
            foreach ($this->validator->validate(\is_string($data) ? $data : null) as $message) {
                $event->getForm()->addError(new FormError($message));
            }
        });
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'ckeditor_dsfr';
    }
}
