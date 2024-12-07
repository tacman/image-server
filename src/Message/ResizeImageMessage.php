<?php

namespace App\Message;

final class ResizeImageMessage
{

     public function __construct(
         private readonly string $filter,
         private readonly string $url,
         private readonly string $callbackUrl,
         private readonly ?string $proxy=null,
     ) {
     }

    public function getCallbackUrl(): string
    {
        return $this->callbackUrl;
    }

    public function getFilter(): string
    {
        return $this->filter;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
