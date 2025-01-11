<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Media;
use App\Message\DownloadImage;
use App\Message\ResizeImageMessage;
use App\Message\SendWebhookMessage;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use Liip\ImagineBundle\Events\CacheResolveEvent;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Cache\Helper\PathHelper;
use Liip\ImagineBundle\Service\FilterService;
use Psr\Log\LoggerInterface;
use Survos\SaisBundle\Service\SaisClientService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\String\u;

class ApiService
{
    public function __construct(
        private readonly FilesystemOperator     $defaultStorage,
        private readonly LoggerInterface        $logger,
        private readonly HttpClientInterface    $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaRepository        $mediaRepository,
        private readonly MessageBusInterface    $messageBus,
        #[Autowire('@liip_imagine.service.filter')]
        private readonly FilterService          $filterService,
        private SerializerInterface             $serializer,
        private NormalizerInterface             $normalizer,
        #[Autowire('%env(SAIS_API_ENDPOINT)%')] private string $apiEndpoint
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
        // the logic from filterAction
        $path = $message->getPath();
        $path = PathHelper::urlPathToFilePath($path);
        $filter = $message->getFilter();

        // this actually _does_ the image creation and returns the url
        $url =  $this->filterService->getUrlOfFilteredImage(
            $path,
            $filter,
            null,
            true
        );
        $this->logger->info(sprintf('%s (%s) has been resolved to %s',
            $path, $filter, $url));

        // update the info in the database?  Seems like the wrong place to do this.
        // although this is slow, it's nice to know the generated size.
        $cachedUrl =  $this->filterService->getUrlOfFilteredImage(
            $path,
            $filter,
            null,
            true
        );

        $request = $this->httpClient->request('GET', $cachedUrl);
        $headers = $request->getHeaders();
        /** @var Media $media */
        $media = $this->mediaRepository->findOneBy(['path' => $path]);
        assert($media, "No media for $path");
        $size = (int)$headers['content-length'][0];
        $media->addFilter($filter, $size);

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
            $code = SaisClientService::calculateCode($url);
            $path = SaisClientService::calculatePath($code);
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
        $code = SaisClientService::calculateCode(url: $url);
        $path = SaisClientService::calculatePath($code);
        $tempFile = SaisClientService::calculateCode($url); // no dirs
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
        // update the database with the downloaded file
        $media = $this->updateDatabase($code, $path, $mimeType, $url, filesize($tempFile));

        // @todo: filters, dispatch a synced message since we're in the download
        foreach ($message->getFilters() as $filter) {
            $this->messageBus->dispatch(
                new ResizeImageMessage($filter, $path),
                stamps: [
                    new TransportNamesStamp('sync')
                ]
            );
        }
        // side effect of resize is that media is updated with the filter sized.
        $this->entityManager->flush();

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
            throw new \Exception("Problem with $url " . $response->getStatusCode());
        }

        $fileHandler = fopen($tempFile, 'w');
        foreach ($client->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }
        fclose($fileHandler);

    }

    private function dispatchWebhook(?string $callbackUrl, Media $media): void
    {
        $content = $this->normalizer->normalize($media);
        $this->messageBus->dispatch( new SendWebhookMessage($callbackUrl, $content) );
    }

    #[AsMessageHandler()]
    public function onWebhookMessage(SendWebhookMessage $message): void
    {
        $request = $this->httpClient->request('POST', $message->getCallbackUrl(),
            [
            'body' => $message->getData(),
//            'proxy' => 'http://127.0.0.1:7080'
        ]);
        $this->logger->info($message->getCallbackUrl() . " returned " . $request->getContent());
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
