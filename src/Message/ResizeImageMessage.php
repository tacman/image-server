<?php

namespace App\Message;

use Liip\ImagineBundle\Service\FilterService;

final class ResizeImageMessage
{

     public function __construct(
         private readonly string  $filter,
         private readonly string  $path,
         private readonly ?string  $code=null, // for access to the media object
         private readonly ?string $callbackUrl=null,
         private readonly ?string $proxy=null

     ) {
     }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getProxy(): ?string
    {
        return $this->proxy;
    }

    public function getCallbackUrl(): string
    {
        return $this->callbackUrl;
    }

    public function getFilter(): string
    {
        return $this->filter;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
