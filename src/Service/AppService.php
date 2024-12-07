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
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\String\u;

class AppService
{
    public function __construct(
        private readonly FilesystemOperator  $defaultStorage,
        private readonly LoggerInterface     $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaRepository $mediaRepository,
        private readonly CacheManager $cacheManager,
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

    public function getMedia(?string $url=null, ?string $path=null): ?Media
    {
        if (!$path) {
            $path = self::calculateCode($url);
        }
        if (!$media = $this->mediaRepository->find($path)) {
            dd($path . " missing from media");

        }
        return $media;
    }

    #[AsMessageHandler()]
    public function onDownloadImage(DownloadImage $message): void
    {
        $code = self::calculateCode($url = $message->getUrl());
        if ($this->defaultStorage->has($code)) {
//            return;
        }

        $client = $this->httpClient;
        $response = $client->request('GET', $url);

// Responses are lazy: this code is executed as soon as headers are received
        if (200 !== $response->getStatusCode()) {
            throw new \Exception("Problem with $url");
        }

// get the response content in chunks and save them in a file
// response chunks implement Symfony\Contracts\HttpClient\ChunkInterface
        $tempFile = $tempFile = str_replace('/','-', $code);
        if (!file_exists($tempFile)) {
            $fileHandler = fopen($tempFile, 'w');
            // locally
            foreach ($client->stream($response) as $chunk) {
                fwrite($fileHandler, $chunk->getContent());
            }
        }

        $mimeType = mime_content_type($tempFile);
        if (!$ext = pathinfo($url, PATHINFO_EXTENSION)) {
            $code .= '.'. u($mimeType)->after('image/');
        }
        // check $ext and extension?
        // download locally first, for analysis,then upload
        $stream = fopen($tempFile, 'r');
        if (!$this->defaultStorage->has($code)) {

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
            $this->logger->info("$url downloaded to " . $code);
        } else {
            $this->logger->info("$url already exists as  $code");
        }
        if (!$media = $this->mediaRepository->find($code)) {
            $media = new Media($code);
            $this->entityManager->persist($media);
        }
        $media
            ->setOriginalUrl($url)
            ->setMimeType($mimeType)
            ->setSize(filesize($tempFile));
        $this->entityManager->flush();

        $callbackUrl = $message->getCallbackUrl();
        $request = $client->request('POST', $callbackUrl, [
            'body' => ['code' => $code, 'size' => $media->getSize()],
            'proxy' => 'http://127.0.0.1:7080'
        ]);
        $this->logger->info($callbackUrl . " returned " . $request->getContent());
//        dd($message->getCallbackUrl(), $request->getStatusCode());
        // and also to defaultStorage
//        fclose($fileHandler);
        // get the size
//        $size = $this->defaultStorage->fileSize($code);


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
