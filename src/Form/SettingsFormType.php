<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Form;

use Yassrzg\CkeditorDsfrBundle\Editor\ComponentRegistry;
use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Yassrzg\CkeditorDsfrBundle\Media\MediaDirectories;
use Yassrzg\CkeditorDsfrBundle\Settings\EditorSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Réglages de l'éditeur (administrateur) : composants proposés, hôtes des lecteurs vidéo, formats, poids maximal
 * et dossier de stockage des images et des documents. Données : tableau au format de EditorSettings::save() (hôtes en texte).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class SettingsFormType extends AbstractType
{
    public function __construct(
        private readonly EditorSettings $settings,
        private readonly ComponentRegistry $registry,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $components = [];
        foreach ($this->registry->selectable() as $definition) {
            $components[$definition->label] = $definition->name;
        }

        $builder->add('enabled_components', ChoiceType::class, [
            'label' => 'Composants proposés dans l\'éditeur',
            'help' => 'Un composant décoché n\'est plus proposé ; les contenus qui l\'utilisent déjà restent affichés. '
                .'La vidéo n\'est proposée que si au moins une plateforme est autorisée.',
            'choices' => $components,
            'choice_attr' => fn (string $name): array => ['data-description' => $this->registry->selectable()[$name]->description],
            'expanded' => true,
            'multiple' => true,
            'required' => false,
        ]);

        $builder->add('video_hosts', TextareaType::class, [
            'label' => 'Plateformes vidéo autorisées',
            'help' => 'Un hôte par ligne : celui du lecteur intégré (ex. www.youtube.com, www.dailymotion.com, player.vimeo.com, '
                .'ou la plateforme vidéo interne). Vide : le composant « Vidéo » n\'est plus proposé et les vidéos ne sont plus affichées.',
            'required' => false,
            'attr' => ['rows' => 5, 'spellcheck' => 'false'],
            'constraints' => [new Assert\Callback(static function (?string $value, ExecutionContextInterface $context): void {
                $invalid = EditorSettings::parseHosts((string) $value)['invalid'];
                if ([] !== $invalid) {
                    $context->addViolation('Hôte non valide : '.implode(', ', $invalid).'. Exemple attendu : www.youtube.com');
                }
            })],
        ]);

        $limit = $this->settings->maxSizeLimit();
        foreach (MediaType::cases() as $type) {
            $catalog = $this->settings->catalog($type);
            $builder->add($type->value.'_extensions', ChoiceType::class, [
                'label' => MediaType::IMAGE === $type ? 'Formats d\'image acceptés' : 'Formats de document acceptés',
                'help' => 'Le format est vérifié d\'après le contenu du fichier, pas d\'après son nom.',
                'choices' => array_combine(array_map('strtoupper', $catalog), $catalog),
                'expanded' => true,
                'multiple' => true,
                'attr' => ['inline' => true],
                'constraints' => [new Assert\Count(min: 1, minMessage: 'Choisissez au moins un format.')],
            ]);
            $builder->add($type->value.'_max_size', IntegerType::class, [
                'label' => MediaType::IMAGE === $type ? 'Poids maximal d\'une image (Mo)' : 'Poids maximal d\'un document (Mo)',
                'help' => \sprintf('Entre 1 et %d Mo. Le serveur doit aussi accepter ce poids (upload_max_filesize de PHP : %s).', $limit, \ini_get('upload_max_filesize') ?: '?'),
                'attr' => ['min' => 1, 'max' => $limit],
                'constraints' => [new Assert\NotBlank(), new Assert\Range(min: 1, max: $limit)],
            ]);
            $builder->add($type->value.'_directory', TextType::class, [
                'label' => MediaType::IMAGE === $type ? 'Dossier des images' : 'Dossier des documents',
                'help' => 'Sous-dossier du dossier racine de la médiathèque (fixé par la configuration du site), ex. « contenu/'.$type->value.'s » : '
                    .'lettres, chiffres, - et _, séparés par /. Un changement déplace les fichiers existants.',
                'attr' => ['maxlength' => 200, 'spellcheck' => 'false', 'autocomplete' => 'off'],
                'constraints' => [new Assert\NotBlank(), new Assert\Callback(static function (?string $value, ExecutionContextInterface $context): void {
                    if (null === MediaDirectories::normalize($value)) {
                        $context->addViolation('Dossier non valide : lettres, chiffres, - et _, séparés par / (ex. contenu/images).');
                    }
                })],
            ]);
        }
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?array $data): ?array => $data,
            static function (?array $data): ?array {
                // Deux catégories dans le même dossier : les noms générés restent uniques, mais le déplacement d'une
                // catégorie emporterait les fichiers de l'autre.
                if (null !== $data && MediaDirectories::normalize($data['image_directory'] ?? '') === MediaDirectories::normalize($data['document_directory'] ?? '')) {
                    throw new TransformationFailedException('Même dossier', invalidMessage: 'Les images et les documents doivent être dans des dossiers différents.');
                }

                return $data;
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'ckeditor_dsfr_settings']);
    }

    public function getBlockPrefix(): string
    {
        return 'ckeditor_dsfr_settings';
    }

    /**
     * Valeurs actuelles pour le formulaire.
     *
     * @return array<string, mixed>
     */
    public static function dataFrom(EditorSettings $settings, ComponentRegistry $registry): array
    {
        return [
            'enabled_components' => array_values(array_diff(array_keys($registry->selectable()), $settings->disabledComponents())),
            'video_hosts' => implode("\n", $settings->videoHosts()),
            'image_extensions' => $settings->extensions(MediaType::IMAGE),
            'image_max_size' => $settings->maxSize(MediaType::IMAGE),
            'document_extensions' => $settings->extensions(MediaType::DOCUMENT),
            'document_max_size' => $settings->maxSize(MediaType::DOCUMENT),
            'image_directory' => $settings->directory(MediaType::IMAGE),
            'document_directory' => $settings->directory(MediaType::DOCUMENT),
        ];
    }

    /**
     * Valeurs saisies au format de EditorSettings::save().
     *
     * @param array<string, mixed> $data
     *
     * @return array{video_hosts: list<string>, image_extensions: list<string>, document_extensions: list<string>, image_max_size: int, document_max_size: int, disabled_components: list<string>, image_directory: string, document_directory: string}
     */
    public static function toSettings(array $data, ComponentRegistry $registry): array
    {
        return [
            'video_hosts' => EditorSettings::parseHosts((string) $data['video_hosts'])['hosts'],
            'image_extensions' => array_values($data['image_extensions']),
            'document_extensions' => array_values($data['document_extensions']),
            'image_max_size' => (int) $data['image_max_size'],
            'document_max_size' => (int) $data['document_max_size'],
            'disabled_components' => array_values(array_diff(array_keys($registry->selectable()), $data['enabled_components'] ?? [])),
            'image_directory' => (string) MediaDirectories::normalize($data['image_directory']),
            'document_directory' => (string) MediaDirectories::normalize($data['document_directory']),
        ];
    }
}
