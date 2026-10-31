<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Form;

use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Yassrzg\CkeditorDsfrBundle\Settings\EditorSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Média de la médiathèque : fichier (obligatoire à l'ajout, facultatif ensuite pour le remplacer) et informations
 * selon la catégorie (texte alternatif d'une image, description d'un document). Formats et poids : réglages de
 * l'administrateur.
 *
 * @extends AbstractType<Media>
 */
final class MediaFormType extends AbstractType
{
    public function __construct(private readonly EditorSettings $settings)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Media $media */
        $media = $options['data'];
        $type = $media->getType();
        $isNew = null === $media->getId();
        $extensions = $this->settings->extensions($type);
        $formats = strtoupper(implode(', ', $extensions));
        $maxSize = $this->settings->maxSize($type);

        $builder
            ->add('file', FileType::class, [
                'label' => $isNew ? 'Fichier' : 'Remplacer le fichier',
                'help' => \sprintf('Formats acceptés : %s. Poids maximal : %d Mo.', $formats ?: 'aucun', $maxSize).($isNew ? '' : ' Le média garde sa place dans les contenus.'),
                'mapped' => false,
                'required' => $isNew,
                'constraints' => [
                    ...($isNew ? [new Assert\NotNull(message: 'Choisissez un fichier.')] : []),
                    new Assert\File(maxSize: $maxSize.'M', extensions: $extensions, extensionsMessage: 'Format non accepté. Formats acceptés : '.$formats.'.'),
                ],
                'attr' => ['accept' => implode(',', array_map(static fn (string $e): string => '.'.$e, $extensions))],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'help' => $media->isImage() ? 'Nom de l\'image dans la médiathèque.' : 'Intitulé du lien de téléchargement.',
                'attr' => ['maxlength' => 255],
            ]);

        if ($media->isImage()) {
            $builder
                ->add('alt', TextType::class, [
                    'label' => 'Texte alternatif',
                    'help' => 'Ce que l\'image apporte au lecteur (lu par les lecteurs d\'écran).',
                    'required' => false,
                    'attr' => ['maxlength' => 500],
                ])
                ->add('decorative', CheckboxType::class, [
                    'label' => 'Image décorative',
                    'help' => 'Sans information utile : aucun texte alternatif n\'est lu.',
                    'required' => false,
                ]);
        } else {
            $builder->add('description', TextareaType::class, [
                'label' => 'Description',
                'help' => 'Facultatif : texte de la carte de téléchargement.',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 500],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Media::class, 'csrf_token_id' => 'ckeditor_dsfr_media']);
    }

    public function getBlockPrefix(): string
    {
        return 'media_form';
    }
}
