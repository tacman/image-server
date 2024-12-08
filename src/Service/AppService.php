<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Media;
use App\Message\DownloadImage;
use App\Message\ResizeImageMessage;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use Liip\ImagineBundle\Events\CacheResolveEvent;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Psr\Log\LoggerInterface;
use Survos\ImageClientBundle\Service\ImageClientService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\String\u;

class AppService
{
    public function __construct(
        private readonly FilesystemOperator     $defaultStorage,
        private readonly LoggerInterface        $logger,
        private readonly HttpClientInterface    $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaRepository        $mediaRepository,
        private readonly CacheManager           $cacheManager,
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
        $path = $message->getPath();
        $filter = $message->getFilter();
        $resolvedPath = $this->cacheManager->getBrowserPath($path, $filter);
        $this->logger->info(sprintf('%s (%s) has been resolved to %s',
            $path, $filter, $resolvedPath));

    }

    public function getMedia(?string $url = null, ?string $path = null): ?Media
    {
        if ($url && $path) {
            throw new \RuntimeException('Cannot have both path and url');
        }
        if (!$url && !$path) {
            throw new \RuntimeException('Must specify a url or path');
        }
        if (!$path) {
            $code = ImageClientService::calculateCode($url);
            $path = ImageClientService::calculatePath($code);
        }
        if (!$media = $this->mediaRepository->find($path)) {
            dd($path . " missing from media");
        }
        return $media;
    }

    #[AsMessageHandler()]
    public function onDownloadImage(DownloadImage $message): void
    {
        $url = $message->getUrl();
        $code = ImageClientService::calculateCode(url: $url);
        $path = ImageClientService::calculatePath($code);
        $tempFile = ImageClientService::calculateCode($url); // no dirs
        if (!file_exists($tempFile)) {
            $this->downloadUrl($url, $tempFile);
        }
        assert(file_exists($tempFile));
        $mimeType = mime_content_type($tempFile);
        $ext = pathinfo($url, PATHINFO_EXTENSION);
        $path .= '.' . ($ext ?: u($mimeType)->after('image/'));

        // upload it to long-term storage
        if (!$this->defaultStorage->has($path)) {
            $this->uploadUrl($tempFile, $path);
            $this->logger->info("$url downloaded to " . $path);
        } else {
            $this->logger->info("$url already exists as  $path");
        }

        // @todo: filters
        $media = $this->updateDatabase($code, $path, $mimeType, $url, filesize($tempFile));
        $this->dispatchWebhook($message->getCallbackUrl(), $media);
    }

    private function uploadUrl(string $tempFile, string $code): void
    {
        $stream = fopen($tempFile, 'r');

        try {
            $config = []; // visibility?
            $directory = pathinfo($code, PATHINFO_DIRNAME);
            if (!$this->defaultStorage->directoryExists($directory)) {
                $this->defaultStorage->createDirectory($directory);
            }
            $this->defaultStorage->writeStream($code, $stream, $config);
        } catch (FilesystemException|UnableToWriteFile $exception) {
            // handle the error
            $this->logger->error($exception->getMessage());
            dd($exception, $code);
        }

    }

    /**
     * writes the URL locally, esp during debugging but also to check the mime type
     *
     * @param string $url
     * @param string $tempFile
     * @return void
     * @throws TransportExceptionInterface
     */
    private function downloadUrl(string $url, string $tempFile): void
    {
        $client = $this->httpClient;
        $response = $client->request('GET', $url);

// Responses are lazy: this code is executed as soon as headers are received
        if (200 !== $response->getStatusCode()) {
            throw new \Exception("Problem with $url");
        }

        $fileHandler = fopen($tempFile, 'w');
        foreach ($client->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }
        fclose($fileHandler);

    }

    private function dispatchWebhook(?string $callbackUrl, Media $media): void
    {
        $this->logger->warning("@todo: dispatch $callbackUrl");
        return;
        $request = $this->httpClient->request('POST', $callbackUrl, [
            'body' => [
                'code' => $media->getPath(),
                'size' => $media->getSize()
            ],
            'proxy' => 'http://127.0.0.1:7080'
        ]);
        $this->logger->info($callbackUrl . " returned " . $request->getContent());

    }

    private function updateDatabase(
        string $code,
        string $path,
        string $mimeType,
        string $url,
        int $size
    ): Media
    {

        if (!$media = $this->mediaRepository->find($code)) {
            $media = new Media($code, $path);
            $this->entityManager->persist($media);
        }
        $media
            ->setOriginalUrl($url)
            ->setMimeType($mimeType)
            ->setSize($size);
        $this->entityManager->flush();
        return $media;

    }


}
