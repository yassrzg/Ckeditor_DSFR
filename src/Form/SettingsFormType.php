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
 * Réglages de l'éditeur (administrateur) : composants proposés (par catégorie), sources des vidéos et plateformes
 * autorisées, formats, poids maximal et dossier de stockage de chaque catégorie de médias (images, documents, vidéos).
 * Données : tableau au format de EditorSettings::save() (hôtes en texte).
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
        // Composants regroupés par catégorie (clé « category » du catalogue YAML), dans l'ordre du catalogue.
        $components = [];
        foreach ($this->registry->selectable() as $definition) {
            $components[$definition->category ?? 'Autres composants'][$definition->label] = $definition->name;
        }

        $builder->add('enabled_components', ChoiceType::class, [
            'label' => 'Composants proposés dans l\'éditeur',
            'help' => 'Un composant décoché n\'est plus proposé ; les contenus qui l\'utilisent déjà restent affichés.',
            'choices' => $components,
            'choice_attr' => fn (string $name): array => [
                'data-description' => $this->registry->selectable()[$name]->description,
                'data-category' => $this->registry->selectable()[$name]->category ?? 'Autres composants',
            ],
            'expanded' => true,
            'multiple' => true,
            'required' => false,
        ]);

        $builder->add('video_sources', ChoiceType::class, [
            'label' => 'Vidéos proposées au rédacteur',
            'help' => 'Aucune case cochée : le composant « Vidéo » n\'est plus proposé (les vidéos déjà insérées restent affichées).',
            'choices' => ['Lien vers une plateforme vidéo' => EditorSettings::VIDEO_LINK, 'Fichier vidéo envoyé dans la médiathèque' => EditorSettings::VIDEO_UPLOAD],
            'choice_attr' => static fn (string $source): array => ['data-description' => EditorSettings::VIDEO_LINK === $source
                ? 'Adresse d\'une vidéo hébergée sur une des plateformes autorisées ci-dessous.'
                : 'Fichier lu par le lecteur du navigateur, avec sous-titres et image d\'aperçu facultatifs.'],
            'expanded' => true,
            'multiple' => true,
            'required' => false,
        ]);

        $builder->add('video_hosts', TextareaType::class, [
            'label' => 'Plateformes vidéo autorisées',
            'help' => 'Un hôte par ligne : celui du lecteur intégré (ex. www.youtube.com, www.dailymotion.com, player.vimeo.com, '
                .'ou la plateforme vidéo interne). Vide : aucune vidéo par lien n\'est proposée ni affichée.',
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
                'label' => 'Formats acceptés',
                'help' => 'Le format est vérifié d\'après le contenu du fichier, pas d\'après son nom.'
                    .(MediaType::DOCUMENT === $type ? ' VTT : sous-titres des vidéos envoyées.' : ''),
                'choices' => array_combine(array_map('strtoupper', $catalog), $catalog),
                'expanded' => true,
                'multiple' => true,
                'attr' => ['inline' => true],
                'constraints' => [new Assert\Count(min: 1, minMessage: 'Choisissez au moins un format.')],
            ]);
            $builder->add($type->value.'_max_size', IntegerType::class, [
                'label' => 'Poids maximal d\'un fichier (Mo)',
                'help' => \sprintf('Entre 1 et %d Mo. Le serveur doit aussi accepter ce poids (PHP : %s par fichier, %s par envoi).', $limit, \ini_get('upload_max_filesize') ?: '?', \ini_get('post_max_size') ?: '?'),
                'attr' => ['min' => 1, 'max' => $limit],
                'constraints' => [new Assert\NotBlank(), new Assert\Range(min: 1, max: $limit)],
            ]);
            $builder->add($type->value.'_directory', TextType::class, [
                'label' => 'Dossier de stockage',
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
                $directories = array_map(static fn (MediaType $type): ?string => MediaDirectories::normalize($data[$type->value.'_directory'] ?? ''), MediaType::cases());
                if (null !== $data && \count($directories) !== \count(array_unique($directories))) {
                    throw new TransformationFailedException('Même dossier', invalidMessage: 'Les images, les documents et les vidéos doivent être dans des dossiers différents.');
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
        $data = [
            'enabled_components' => array_values(array_diff(array_keys($registry->selectable()), $settings->disabledComponents())),
            'video_sources' => $settings->videoSources(),
            'video_hosts' => implode("\n", $settings->videoHosts()),
        ];
        foreach (MediaType::cases() as $type) {
            $data[$type->value.'_extensions'] = $settings->extensions($type);
            $data[$type->value.'_max_size'] = $settings->maxSize($type);
            $data[$type->value.'_directory'] = $settings->directory($type);
        }

        return $data;
    }

    /**
     * Valeurs saisies au format de EditorSettings::save().
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function toSettings(array $data, ComponentRegistry $registry): array
    {
        $settings = [
            'video_sources' => array_values(array_intersect(EditorSettings::VIDEO_SOURCE_CHOICES, $data['video_sources'] ?? [])),
            'video_hosts' => EditorSettings::parseHosts((string) $data['video_hosts'])['hosts'],
            'disabled_components' => array_values(array_diff(array_keys($registry->selectable()), $data['enabled_components'] ?? [])),
        ];
        foreach (MediaType::cases() as $type) {
            $settings[$type->value.'_extensions'] = array_values($data[$type->value.'_extensions']);
            $settings[$type->value.'_max_size'] = (int) $data[$type->value.'_max_size'];
            $settings[$type->value.'_directory'] = (string) MediaDirectories::normalize($data[$type->value.'_directory']);
        }

        return $settings;
    }
}
