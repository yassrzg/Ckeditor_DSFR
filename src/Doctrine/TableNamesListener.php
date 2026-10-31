<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Doctrine;

use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Yassrzg\CkeditorDsfrBundle\Entity\Setting;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Id\SequenceGenerator;

/**
 * Noms des tables du bundle (ckeditor_dsfr.tables) : un projet peut reprendre des tables existantes de même structure.
 * L'index de la catégorie des médias suit le nom de la table (idx_<table>_type).
 */
final readonly class TableNamesListener
{
    /**
     * @param array{media: string, setting: string} $tables
     */
    public function __construct(
        private array $tables,
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();
        $table = match ($metadata->getName()) {
            Media::class => $this->tables['media'],
            Setting::class => $this->tables['setting'],
            default => null,
        };
        if (null === $table) {
            return;
        }

        $metadata->setPrimaryTable(['name' => $table]);
        if (Media::class === $metadata->getName()) {
            $metadata->table['indexes'] = ['idx_'.$table.'_type' => ['columns' => ['type']]];
            // Séquence éventuelle (stratégie SEQUENCE, ex. PostgreSQL avec identity_generation_preferences) : nommée
            // d'après la table, comme Doctrine le fait par défaut (générateur déjà créé avec l'ancien nom : remplacé).
            if ($metadata->isIdGeneratorSequence()) {
                $metadata->sequenceGeneratorDefinition = ['sequenceName' => $table.'_id_seq', 'allocationSize' => '1', 'initialValue' => '1'];
                $metadata->setIdGenerator(new SequenceGenerator($table.'_id_seq', 1));
            }
        }
    }
}
