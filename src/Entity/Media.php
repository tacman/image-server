<?php

namespace App\Entity;

use App\Repository\MediaRepository;
use App\Workflow\IMediaWorkflow;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Survos\CoreBundle\Entity\RouteParametersInterface;
use Survos\CoreBundle\Entity\RouteParametersTrait;
use Survos\SaisBundle\Service\SaisClientService;
use Gedmo\Mapping\Annotation as Gedmo;
use Survos\WorkflowBundle\Traits\MarkingInterface;
use Survos\WorkflowBundle\Traits\MarkingTrait;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Thumbhash\Thumbhash;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
class Media implements MarkingInterface, \Stringable, RouteParametersInterface, IMediaWorkflow
{
    use MarkingTrait;
    use RouteParametersTrait;
    public const UNIQUE_PARAMETERS = ['mediaId' => 'code'];

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    #[ORM\Column(nullable: true, options: ['jsonb' => true])]
    #[Groups(['media.read'])]
    private ?array $thumbData = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on:"create")]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Gedmo\Timestampable(on:"update")]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Thumb>
     */
    #[ORM\OneToMany(targetEntity: Thumb::class, mappedBy: 'media', orphanRemoval: true)]
    private Collection $thumbs;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['media.read'])]
    private ?string $blur = null;

    /**
     * @param string|null $path
     */
    public function __construct(
        #[ORM\Column(length: 32, nullable: false)]
        private readonly string $root,

        #[ORM\Id]
        #[ORM\Column(length: 255)]
        #[Groups(['media.read'])]
        private ?string         $code=null, // includes root!
        #[ORM\Column(length: 255, nullable: true)]
        #[Groups(['media.read'])]
        private ?string         $path=null,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        #[Groups(['media.read'])]
        private ?string         $originalUrl=null
    )
    {
        // cannot change the root, since it creates the file in storage there.  Code is also based on it.
        if ($this->originalUrl && !$this->code) {
            $this->code = SaisClientService::calculateCode(url: $this->originalUrl, root: $this->root);

        }
        $this->thumbs = new ArrayCollection();
        $this->marking = IMediaWorkflow::PLACE_NEW;
    }

    public function getRoot(): string
    {
        return $this->root;
    }


    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getOriginalUrl(): ?string
    {
        return $this->originalUrl;
    }

    public function setOriginalUrl(?string $originalUrl): static
    {
        $this->originalUrl = $originalUrl;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getThumbData(): ?array
    {
        return $this->thumbData??[];
    }

    public function setThumbData(?array $thumbData): static
    {
        $this->thumbData = $thumbData;

        return $this;
    }

    public function addThumbData($filter, ?int $size = null, ?string $url=null): static
    {
        $filters = $this->getThumbData()??[];
        if (!in_array($filter, $filters, true)) {
            $filters[$filter] = [
                'size' => $size,
                'url' => $url
                ];
        }
        $this->setThumbData($filters);
        return $this;

    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Thumb>
     */
    public function getThumbs(): Collection
    {
        return $this->thumbs;
    }

    public function addResizedImage(Thumb $resizedImage): static
    {
        if (!$this->thumbs->contains($resizedImage)) {
            $this->thumbs->add($resizedImage);
            $resizedImage->setMedia($this);
        }

        return $this;
    }

    public function removeResizedImage(Thumb $resizedImage): static
    {
        if ($this->thumbs->removeElement($resizedImage)) {
            // set the owning side to null (unless already changed)
            if ($resizedImage->getMedia() === $this) {
                $resizedImage->setMedia(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->getCode();
    }

    public function getBlur(): ?string
    {
        return $this->blur;
    }

    public function setBlur(?string $blur): static
    {
        $this->blur = $blur;

        return $this;
    }

    public function getBlurData(): ?array
    {
        return Thumbhash::convertStringToHash($this->getBlur());
    }

    public function getId(): string
    {
        return $this->getCode();
    }
}
