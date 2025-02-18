<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ThumbRepository;
use App\Workflow\IResizedWorkflow;
use Doctrine\ORM\Mapping as ORM;
use Survos\WorkflowBundle\Traits\MarkingInterface;
use Survos\WorkflowBundle\Traits\MarkingTrait;

#[ORM\Entity(repositoryClass: ThumbRepository::class)]
#[ApiResource]
class Thumb implements MarkingInterface, \Stringable
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
