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
use Survos\ThumbHashBundle\Service\ThumbHashService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Thumbhash\Thumbhash;
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
        $media = $this->mediaRepository->findOneBy(['code' => $message->getCode()]);
        assert($media, "No media for $path / " . $message->getCode());
        $size = (int)$headers['content-length'][0];
        $media->addFilter($filter, $size, $url);

        if ($filter == 'tiny') {
            $service = new ThumbHashService();
            $content = $request->getContent();
            list($width, $height, $pixels) = $service->extract_size_and_pixels_with_imagick($content);

            $hash = Thumbhash::RGBAToHash($width, $height, $pixels);
            $key = Thumbhash::convertHashToString($hash); // You can store this in your database as a string
            $media
                ->setBlurData($hash)
                ->setBlur($key);
//            $url = Thumbhash::toDataURL($hash); // use in twig

        }
        $this->entityManager->flush();

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




    private function dispatchWebhook(?string $callbackUrl, Media $media): void
    {
        if ($callbackUrl) {
            $content = $this->normalizer->normalize($media);
            $this->messageBus->dispatch( new SendWebhookMessage($callbackUrl, $content) );
        }
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



    public function updateDatabase(
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
            ->setPath($path)
            ->setOriginalUrl($url)
            ->setMimeType($mimeType)
            ->setSize($size);
        $this->entityManager->flush();
        return $media;

    }


}
