<?php

namespace App\Message;

final class DownloadImage
{

     public function __construct(
         private readonly string $url,
         private ?string $code=null, // media code, not file code
         private readonly array $filters=[],
         private readonly ?string $callbackUrl=null,
         private readonly ?string $proxy='127.0.0.1:7080',
     ) {
     }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getCallbackUrl(): ?string
    {
        return $this->callbackUrl;
    }

    public function getProxy(): ?string
    {
        return $this->proxy;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
