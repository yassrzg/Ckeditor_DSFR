<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Repository;

use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    /**
     * Médias d'une catégorie (toutes si null) dont le titre ou le nom de fichier contient le terme, les plus récents d'abord.
     *
     * @return list<Media>
     */
    public function search(?MediaType $type = null, string $term = '', int $offset = 0, ?int $limit = null): array
    {
        return $this->filtered($type, $term)
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countSearch(?MediaType $type = null, string $term = ''): int
    {
        return (int) $this->filtered($type, $term)->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array<string, int> catégorie (valeur de MediaType) => nombre de médias
     */
    public function countByType(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('m.type AS type, COUNT(m.id) AS total')
            ->groupBy('m.type')
            ->getQuery()
            ->getArrayResult();

        $counts = array_fill_keys(array_map(static fn (MediaType $t): string => $t->value, MediaType::cases()), 0);
        foreach ($rows as $row) {
            $type = $row['type'] instanceof MediaType ? $row['type']->value : (string) $row['type'];
            $counts[$type] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, Media> médias indexés par identifiant (un seul accès à la base pour tout un contenu)
     */
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $medias = [];
        foreach ($this->findBy(['id' => array_values(array_unique($ids))]) as $media) {
            $medias[(int) $media->getId()] = $media;
        }

        return $medias;
    }

    /**
     * @return list<string> noms des fichiers stockés d'une catégorie
     */
    public function filenames(MediaType $type): array
    {
        return array_column($this->createQueryBuilder('m')->select('m.filename')->where('m.type = :type')
            ->setParameter('type', $type)->getQuery()->getScalarResult(), 'filename');
    }

    private function filtered(?MediaType $type, string $term): QueryBuilder
    {
        $qb = $this->createQueryBuilder('m');
        if (null !== $type) {
            $qb->andWhere('m.type = :type')->setParameter('type', $type);
        }
        if ('' !== trim($term)) {
            $qb->andWhere('LOWER(m.title) LIKE :term OR LOWER(m.originalName) LIKE :term')
                ->setParameter('term', '%'.addcslashes(mb_strtolower(trim($term)), '%_\\').'%');
        }

        return $qb;
    }
}
