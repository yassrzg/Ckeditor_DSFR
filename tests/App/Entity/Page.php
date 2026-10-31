<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Tests\App\Entity;

use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Contenu du projet de test : texte saisi avec l'éditeur et vignette de la médiathèque.
 */
#[ORM\Entity]
class Page
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?Media $thumbnail = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        public string $title,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        public ?string $content = null,
    ) {
    }
}
