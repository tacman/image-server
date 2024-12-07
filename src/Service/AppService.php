<?php

declare(strict_types=1);

namespace App\Service;

use App\Message\DownloadImage;
use App\Message\ResizeImageMessage;
use League\Flysystem\FilesystemOperator;
use Liip\ImagineBundle\Events\CacheResolveEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AppService
{
    public function __construct(
        private readonly FilesystemOperator $defaultStorage,
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
    )
    {
    }

    #[AsEventListener()]
    public function onCacheResolve(CacheResolveEvent $event): void
    {
        dd($event);
    }

    #[AsMessageHandler()]
    public function onResizeImage(ResizeImageMessage $message): void
    {
        $code = self::calculateCode($message->getUrl());
    }

    #[AsMessageHandler()]
    public function onDownloadImage(DownloadImage $message): void
    {
        $code = self::calculateCode($url = $message->getUrl());
        if ($this->defaultStorage->has($code)) {
            return;
        }

        $client = $this->httpClient;
        $response = $client->request('GET', $url);

// Responses are lazy: this code is executed as soon as headers are received
        if (200 !== $response->getStatusCode()) {
            throw new \Exception("Problem with $url");
        }

// get the response content in chunks and save them in a file
// response chunks implement Symfony\Contracts\HttpClient\ChunkInterface
        $filename = "data/$code";
        $fileHandler = fopen($tempFile = 'tempfile', 'w');
        // locally
        foreach ($client->stream($response) as $chunk) {
            dump(strlen($chunk->getContent()));
            fwrite($fileHandler, $chunk->getContent());
        }
        // and also to defaultStorage
        fclose($fileHandler);
        $this->logger->info("$url downloaded to $tempFile");

    }

    static public function calculateCode(string $url): string
    {
        $xxh3 = hash('xxh3', $url);
        return sprintf("%s/%s/%s",
            substr($xxh3, 0, 2),
            substr($xxh3, 2, 2),
            substr($xxh3, 4, -1));
    }

}
