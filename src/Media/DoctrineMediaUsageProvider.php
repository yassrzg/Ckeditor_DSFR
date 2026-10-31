<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Media;

use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Usages déclarés dans ckeditor_dsfr.media_usages : entités dont un champ de l'éditeur cite le média
 * (data-media-id="12", data-poster-media-id="12", data-media-ids="4,12,7") ou dont une relation pointe vers lui (vignette…).
 */
final readonly class DoctrineMediaUsageProvider implements MediaUsageProviderInterface
{
    private PropertyAccessorInterface $accessor;

    /**
     * @param list<array{class: class-string, label: string, content_fields: list<string>, relation_fields: list<string>, route: ?string, route_parameters: array<string, string>}> $declarations
     */
    public function __construct(
        private ManagerRegistry $registry,
        private UrlGeneratorInterface $urlGenerator,
        private array $declarations,
    ) {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    public function usages(Media $media): array
    {
        $usages = [];
        foreach ($this->declarations as $declaration) {
            foreach ($this->find($declaration, $media) as $entity) {
                $label = (string) $this->accessor->getValue($entity, $declaration['label']);
                $url = null === $declaration['route'] ? null : $this->urlGenerator->generate($declaration['route'], array_map(
                    fn (string $property): mixed => $this->accessor->getValue($entity, $property),
                    $declaration['route_parameters'],
                ));
                $usages[] = new MediaUsage($label, $url);
            }
        }

        return $usages;
    }

    /**
     * @param array{class: class-string, label: string, content_fields: list<string>, relation_fields: list<string>, route: ?string, route_parameters: array<string, string>} $declaration
     *
     * @return list<object>
     */
    private function find(array $declaration, Media $media): array
    {
        $manager = $this->registry->getManagerForClass($declaration['class']);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(\sprintf('ckeditor_dsfr.media_usages : %s n\'est pas une entité Doctrine ORM.', $declaration['class']));
        }
        $conditions = [];
        $qb = $manager->createQueryBuilder()->select('e')->from($declaration['class'], 'e');

        foreach ($declaration['relation_fields'] as $field) {
            $conditions[] = 'e.'.$field.' = :media';
            $qb->setParameter('media', $media);
        }
        $id = (string) $media->getId();
        foreach ($declaration['content_fields'] as $i => $field) {
            // Un média (data-media-id, data-poster-media-id…) ou une liste « 12,4,7 » (data-media-ids) : seul, premier,
            // au milieu ou dernier.
            foreach (['one' => '%media-id="'.$id.'"%', 'alone' => '%media-ids="'.$id.'"%', 'first' => '%media-ids="'.$id.',%',
                'middle' => '%media-ids="%,'.$id.',%', 'last' => '%media-ids="%,'.$id.'"%'] as $key => $pattern) {
                $conditions[] = 'e.'.$field.' LIKE :'.$key.$i;
                $qb->setParameter($key.$i, $pattern);
            }
        }
        if ([] === $conditions) {
            return [];
        }

        return $qb->where(implode(' OR ', $conditions))->orderBy('e.'.$declaration['label'], 'ASC')->getQuery()->getResult();
    }
}
