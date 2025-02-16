<?php

namespace App\Entity;

use App\Repository\MediaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Survos\SaisBundle\Service\SaisClientService;
use Gedmo\Mapping\Annotation as Gedmo;
#[ORM\Entity(repositoryClass: MediaRepository::class)]
class Media
{


    #[ORM\Column(length: 16, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    #[ORM\Column(nullable: true)]
    private ?array $filters = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on:"create")]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Gedmo\Timestampable(on:"update")]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @param string|null $path
     */
    public function __construct(
        #[ORM\Column(length: 32, nullable: false)]
        private readonly string $root,

        #[ORM\Id]
        #[ORM\Column(length: 255)]
        private ?string         $code=null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string         $path=null,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string         $originalUrl=null
    )
    {
        // cannot change the root, since it creates the file in storage there.  Code is also based on it.
        if ($this->originalUrl && !$this->code) {
            $this->code = SaisClientService::calculateCode(url: $this->originalUrl . $this->root);

        }
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

    public function getFilters(): ?array
    {
        return $this->filters;
    }

    public function setFilters(?array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function addFilter($filter, ?int $size = null, ?string $url=null): static
    {
        $filters = $this->getFilters()??[];
        if (!in_array($filter, $filters, true)) {
            $filters[$filter] = [
                'size' => $size,
                'url' => $url
                ];
        }
        $this->setFilters($filters);
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
}
