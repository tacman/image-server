<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Media;
use App\Message\DownloadImage;
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
        private readonly LoggerInterface                          $logger,
        private readonly HttpClientInterface                      $httpClient,
        private readonly HttpClientInterface                      $localHttpClient,
        private readonly EntityManagerInterface                   $entityManager,
        private readonly MediaRepository                          $mediaRepository,
        private readonly MessageBusInterface                      $messageBus,
        #[Autowire('@liip_imagine.service.filter')]
        private readonly FilterService                            $filterService,
        private SerializerInterface                               $serializer,
        private NormalizerInterface                               $normalizer,
        #[Autowire('%env(HTTP_PROXY)%')] private readonly ?string $proxyUrl,
        #[Autowire('%env(SAIS_API_ENDPOINT)%')] private string    $apiEndpoint,
        private readonly SaisClientService $saisClientService,
    ) {
        if ($proxyUrl) {
            assert(!str_contains($proxyUrl, 'http'), "no scheme in the proxy!");
        }
    }

    #[AsEventListener()]
    public function onCacheResolve(CacheResolveEvent $event): void
    {
        dd($event);
    }

    public function getMedia(?string $url = null, ?string $path = null): ?Media
    {
        assert(false, "need root?");
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
            $env = $this->messageBus->dispatch( new SendWebhookMessage($callbackUrl, $content) );
            dd($env);
        }
    }

    #[AsMessageHandler()]
    public function onWebhookMessage(SendWebhookMessage $message): void
    {
        return;
        $options = [
            'timeout' => 4,
            'json' => $this->normalizer->normalize($message->getData()),
//            'proxy' => $this->proxyUrl,
        ];
        $url = $message->getCallbackUrl();
//        $url = 'https://d5b2-2607-fb91-870-3d2-1769-9a25-d2a2-96b7.ngrok-free.app/handle_media';
        $url = 'https://md.wip/webhook';
//        dd($message, $this->proxyUrl, $message->getData(), $options);
//        $x = file_get_contents($url); dd($x);
        dump($url, $options);
        $request = $this->httpClient->request('POST', $url, $options);
        if ($request->getStatusCode() !== 200) {
            dd($message, $url, $request->getStatusCode());
        }
        dd($request->getStatusCode(), response: $request->toArray());
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
