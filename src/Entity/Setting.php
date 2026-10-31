<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Entity;

use Yassrzg\CkeditorDsfrBundle\Repository\SettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Réglage modifiable par l'administrateur (hôtes vidéo, formats et poids des médias, composants désactivés).
 * Une ligne par réglage, identifiée par son nom. Nom de la table : ckeditor_dsfr.tables.setting (TableNamesListener) ;
 * la table peut être partagée avec d'autres réglages du projet (seuls les noms de EditorSettings sont lus).
 */
#[ORM\Entity(repositoryClass: SettingRepository::class)]
#[ORM\Table(name: 'ckeditor_dsfr_setting')]
class Setting
{
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $value = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 100)]
        private string $name,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
