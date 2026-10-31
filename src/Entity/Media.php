<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Entity;

use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Yassrzg\CkeditorDsfrBundle\Repository\MediaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Média de la médiathèque (image ou document), utilisable dans les contenus de l'éditeur.
 * Nom de la table : ckeditor_dsfr.tables.media (TableNamesListener). Le fichier est stocké dans
 * ckeditor_dsfr.storage_directory (MediaStorage) et servi par MediaController.
 */
#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'ckeditor_dsfr_media')]
#[ORM\Index(name: 'idx_ckeditor_dsfr_media_type', columns: ['type'])]
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: MediaType::class)]
    private MediaType $type;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 255)]
    private string $title = '';

    /** Texte d'une carte de téléchargement (documents). */
    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $description = null;

    /** Texte alternatif (images) ; vide si l'image est décorative. */
    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $alt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $decorative = false;

    /** Nom du fichier stocké (généré, sans chemin). */
    #[ORM\Column(length: 255)]
    private string $filename = '';

    #[ORM\Column(length: 255)]
    private string $originalName = '';

    #[ORM\Column(length: 100)]
    private string $mimeType = '';

    #[ORM\Column]
    private int $size = 0;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(MediaType $type)
    {
        $this->type = $type;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateAlternative(ExecutionContextInterface $context): void
    {
        // RGAA : une image porteuse d'information a un texte alternatif.
        if (MediaType::IMAGE === $this->type && !$this->decorative && '' === trim((string) $this->alt)) {
            $context->buildViolation('Le texte alternatif est obligatoire, sauf pour une image décorative.')->atPath('alt')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): MediaType
    {
        return $this->type;
    }

    public function isImage(): bool
    {
        return MediaType::IMAGE === $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = trim((string) $title);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = '' === trim((string) $description) ? null : trim((string) $description);

        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): static
    {
        $this->alt = '' === trim((string) $alt) ? null : trim((string) $alt);

        return $this;
    }

    /** Texte alternatif à afficher : vide pour une image décorative. */
    public function getAltText(): string
    {
        return $this->decorative ? '' : (string) $this->alt;
    }

    public function isDecorative(): bool
    {
        return $this->decorative;
    }

    public function setDecorative(bool $decorative): static
    {
        $this->decorative = $decorative;

        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    /**
     * Fichier stocké (à l'envoi ou au remplacement).
     */
    public function setFile(string $filename, string $originalName, string $mimeType, int $size, ?int $width = null, ?int $height = null): static
    {
        $this->filename = $filename;
        $this->originalName = mb_substr($originalName, 0, 255);
        $this->mimeType = mb_substr($mimeType, 0, 100);
        $this->size = $size;
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    /** Format affiché (« PDF », « DOCX ») : extension du fichier stocké. */
    public function getFormat(): string
    {
        return strtoupper(pathinfo($this->filename, \PATHINFO_EXTENSION));
    }

    /** Poids lisible (« 45 Ko », « 2,1 Mo »), exigé par le RGAA sur un lien de téléchargement. */
    public function getSizeLabel(): string
    {
        return match (true) {
            $this->size < 1024 => $this->size.' o',
            $this->size < 1024 ** 2 => round($this->size / 1024).' Ko',
            default => str_replace('.', ',', (string) round($this->size / 1024 ** 2, 1)).' Mo',
        };
    }

    /** « PDF – 2,1 Mo » */
    public function getDetail(): string
    {
        return $this->getFormat().' – '.$this->getSizeLabel();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
