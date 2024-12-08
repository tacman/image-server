<?php

namespace App\Message;

final class DownloadImage
{

     public function __construct(
         private readonly string $url,
         private readonly ?string $callbackUrl=null,
         private readonly ?string $proxy='http://127.0.0.1:7080',
     ) {
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
