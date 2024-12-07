<?php

namespace App\MessageHandler;

use App\Message\ResizeImageMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ResizeImageMessageHandler
{
    public function __invoke(ResizeImageMessage $message): void
    {
        // do something with your message
    }
}
