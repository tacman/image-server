<?php

namespace App\Message;

use Liip\ImagineBundle\Service\FilterService;

final class SendWebhookMessage
{

     public function __construct(
         private ?string $callbackUrl,
         private array $data
     ) {
     }

    public function getCallbackUrl(): string
    {
        return $this->callbackUrl;
    }

    public function getData(): array
    {
        return $this->data;
    }

}
