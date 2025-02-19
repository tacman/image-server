<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\ThumbRepository;
use App\Workflow\ThumbWorkflowInterface;
use Doctrine\ORM\Mapping as ORM;
use Survos\WorkflowBundle\Traits\MarkingInterface;
use Survos\WorkflowBundle\Traits\MarkingTrait;
use Zenstruck\Alias;

#[ORM\Entity(repositoryClass: ThumbRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['media.read']],
        ),
        new GetCollection(
            normalizationContext: ['groups' => ['media.read']],
        )
    ]
)]
#[ApiFilter(filterClass: SearchFilter::class, properties: [
    'liipCode' => 'exact',
    'marking' => 'exact',
    'media' => 'exact',
])]
#[Alias('thumb')]
class Thumb implements MarkingInterface, \Stringable, ThumbWorkflowInterface
{
    use MarkingTrait;


    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    #[ORM\Column(nullable: true)]
    private ?int $w = null;

    #[ORM\Column(nullable: true)]
    private ?int $h = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'resizedImages')]
        #[ORM\JoinColumn(referencedColumnName: 'code', nullable: false)]
        private ?Media  $media = null,

        #[ORM\Column(length: 16)]
        private ?string $liipCode = null,

    )
    {
        if ($this->media) {
            $media->addThumb($this);
        }
        $this->marking = 'new';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMedia(): ?Media
    {
        return $this->media;
    }

    public function setMedia(?Media $media): static
    {
        $this->media = $media;

        return $this;
    }

    public function getLiipCode(): ?string
    {
        return $this->liipCode;
    }

    public function setLiipCode(string $liipCode): static
    {
        $this->liipCode = $liipCode;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getW(): ?int
    {
        return $this->w;
    }

    public function setW(?int $w): static
    {
        $this->w = $w;

        return $this;
    }

    public function getH(): ?int
    {
        return $this->h;
    }

    public function setH(?int $h): static
    {
        $this->h = $h;

        return $this;
    }

    public function __toString(): string
    {
        return $this->getMedia() . '-' . $this->getLiipCode();
        // TODO: Implement __toString() method.
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }
}
