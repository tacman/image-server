<?php

namespace App\Handler;

use App\Entity\Resized;
use App\Message\DownloadImage;
use App\Message\ResizeImageMessage;
use App\Repository\MediaRepository;
use App\Repository\ResizedRepository;
use App\Service\ApiService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use Liip\ImagineBundle\Service\FilterService;
use Psr\Log\LoggerInterface;
use Survos\SaisBundle\Service\SaisClientService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Thumbhash\Thumbhash;
use function Symfony\Component\String\u;

class DownloadImageHandler
{

    public function __construct(
        private readonly FilesystemOperator     $defaultStorage,
        private readonly LoggerInterface        $logger,
        private readonly HttpClientInterface    $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly ApiService $apiService,
        private readonly MediaRepository        $mediaRepository,

        private readonly ResizedRepository $resizedRepository,
        private readonly MessageBusInterface    $messageBus,
        #[Autowire('@liip_imagine.service.filter')]
        private readonly FilterService          $filterService,
        private SerializerInterface             $serializer,
        private NormalizerInterface             $normalizer,
        #[Autowire('%env(SAIS_API_ENDPOINT)%')] private string $apiEndpoint
    )
    {
    }

    #[AsMessageHandler()]
    public function onDownloadImage(DownloadImage $message): void
    {
        $url = $message->getUrl();
        // smell test...
        $code = SaisClientService::calculateCode(url: $url);
        $path = $message->getRoot() . '/' . SaisClientService::calculatePath($code);
        $tempFile = SaisClientService::calculateCode($url); // no dirs

        if (!file_exists($tempFile)) {
            $this->downloadUrl($url, $tempFile);
        }

        $content = file_get_contents($tempFile);

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
        $media = $this->apiService->updateDatabase($code, $path, $mimeType, $url, filesize($tempFile));

        // @todo: filters, dispatch a synced message since we're in the download
        foreach ($message->getFilters() as $filter) {
            if (!$resized = $this->resizedRepository->findOneBy([
                'media' => $media,
                'liipCode' => $filter
            ])) {
                $resized = new Resized();
            }
            // sync because we're already inside of a message, though we could distribute these
            $envelope = $this->messageBus->dispatch(
                new ResizeImageMessage($filter, $path, code: $code),
                stamps: [
                    new TransportNamesStamp('sync')
                ]
            );
            $this->logger->warning(sprintf('Resizing %s (%s)', $path, $filter));
        }
        // side effect of resize is that media is updated with the filter sized.
        $this->entityManager->flush();
        // probably too early
//        $this->dispatchWebhook($message->getCallbackUrl(), $media);
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
    private function downloadUrl(string $url, string $tempFile): string
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
        return $tempFile;

    }


}
