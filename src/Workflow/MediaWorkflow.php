<?php

namespace App\Workflow;

use App\Entity\Media;
use App\Entity\Thumb;
use App\Message\DownloadImage;
use App\Message\ResizeImageMessage;
use App\Message\SendWebhookMessage;
use App\Repository\MediaRepository;
use App\Repository\ThumbRepository;
use App\Service\ApiService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use Liip\ImagineBundle\Service\FilterService;
use Psr\Log\LoggerInterface;
use Survos\SaisBundle\Message\MediaUploadMessage;
use Survos\SaisBundle\Model\DownloadPayload;
use Survos\SaisBundle\Model\MediaModel;
use Survos\SaisBundle\Service\SaisClientService;
use Survos\WorkflowBundle\Attribute\Workflow;
use Survos\WorkflowBundle\Message\AsyncTransitionMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\String\u;

#[Workflow(supports: [Media::class], name: self::WORKFLOW_NAME)]
class MediaWorkflow implements IMediaWorkflow
{
    public const WORKFLOW_NAME = 'MediaWorkflow';

    public function __construct(
        private MessageBusInterface          $messageBus,
        private EntityManagerInterface       $entityManager,
        private ThumbRepository              $thumbRepository,
        private readonly FilesystemOperator  $defaultStorage,
        private readonly LoggerInterface     $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly ApiService          $apiService,
        private readonly MediaRepository     $mediaRepository,
        #[Target(Thumb::WORKFLOW_NAME)] private WorkflowInterface $thumbWorkflow,

        #[Autowire('@liip_imagine.service.filter')]
        private readonly FilterService       $filterService,
        private SerializerInterface          $serializer,
        private NormalizerInterface          $normalizer,
        #[Autowire('%env(SAIS_API_ENDPOINT)%')] private string $apiEndpoint

    )
    {
    }


    #[AsGuardListener(self::WORKFLOW_NAME)]
    public function onGuard(GuardEvent $event): void
    {
        /** @var Media media */
        $media = $event->getSubject();

        switch ($event->getTransition()->getName()) {
            /*
            e.g.
            if ($event->getSubject()->cannotTransition()) {
              $event->setBlocked(true, "reason");
            }
            App\Entity\Media
            */
            case self::TRANSITION_DOWNLOAD:
                break;
            case self::TRANSITION_RESIZE:
                break;
        }
    }

    private function getMedia(TransitionEvent|CompletedEvent $event): Media
    {
        /** @var Media media */
        return $event->getSubject();
    }

    #[AsCompletedListener(self::WORKFLOW_NAME, IMediaWorkflow::TRANSITION_DOWNLOAD)]
    public function onCompleted(CompletedEvent $event): void
    {
        $media = $this->getMedia($event);
        $this->resizeMedia($media, $event->getContext()['liipCodes']);

        // eventually, when the download is complete, dispatch a webhook
//        $env = $this->messageBus->dispatch(new MediaModel(
//            $media->getOriginalUrl(), $media->getRoot(), $media->getPath(), $media->getCode()));
//        return;
//
//        $callbackUrl = match ($media->getRoot()) {
//            'test' => 'https://sais.wip/handle_media'
//        };
//        $envelope = $this->messageBus->dispatch(new SendWebhookMessage($callbackUrl,
//            new DownloadPayload($media->getCode(), $media->getThumbData())
//        ));
//        return;
////        $this->normalizer->normalize($media, 'object', ['groups' => ['media.read']]),
//        dd($envelope, $event->getContext(), $media->getMarking());



        // dispatch the callback request
    }

    private function resizeMedia(Media $media, array $liipCodes): void
    {
        $stamps = [];
        $stamps[] = new TransportNamesStamp('resize');
        foreach ($liipCodes as $filter) {
            if (!$thumb = $this->thumbRepository->findOneBy([
                'media' => $media,
                'liipCode' => $filter,
            ])) {
                $thumb = new Thumb($media, $filter);
                $media->addThumb($thumb);
                $this->entityManager->persist($thumb);
                $this->entityManager->flush();
            }
            $resizedImages[] = $thumb;
            if ($this->thumbWorkflow->can($thumb, $thumb::TRANSITION_RESIZE)) {
                // now dispatch a message to do the resize
                $envelope = $this->messageBus->dispatch(new AsyncTransitionMessage(
                    $thumb->getId(),
                    $thumb::class,
                    ThumbWorkflowInterface::TRANSITION_RESIZE,
                    ThumbWorkflowInterface::WORKFLOW_NAME,
                ), $stamps);
            }
        }

    }

    #[AsTransitionListener(self::WORKFLOW_NAME, IMediaWorkflow::TRANSITION_RESIZE)]
    public function onResize(TransitionEvent $event): void
    {
        $media = $this->getMedia($event);
        $this->resizeMedia($media, ['tiny','medium','large']);
    }

    #[AsTransitionListener(self::WORKFLOW_NAME, IMediaWorkflow::TRANSITION_DOWNLOAD)]
    public function onDownload(TransitionEvent $event): void
    {
        /** @var Media media */
        $media = $this->getMedia($event);
        $url = $media->getOriginalUrl();

        $path = $media->getRoot() . '/' . SaisClientService::calculatePath($media->getCode());
        $tempFile = $media->getCode() . '.' . pathinfo($url, PATHINFO_EXTENSION);// no dirs
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

        $media
            ->setPath($path)
            ->setOriginalUrl($url)
            ->setMimeType($mimeType)
            ->setSize(filesize($tempFile));

        return;

        dd($event->getContext());
        $existingFilters = $media->getFilters();

        // @todo: filters, dispatch a synced message since we're in the download
        foreach ($message->getFilters() as $filter) {

            if (!$resized = $this->thumbRepository->findOneBy([
                'media' => $media,
                'liipCode' => $filter
            ])) {
                $resized = new Thumb($media, $filter);
                $this->entityManager->persist($resized);
            }
        }
        // side effect of resize is that media is updated with the filter sized.
        $this->entityManager->flush();
        // probably too early
//        $this->dispatchWebhook($message->getCallbackUrl(), $media);
//        $envelope = $this->messageBus->dispatch(
//            $msg = new DownloadImage($media->getOriginalUrl(),
//                $media->getRoot(),
//                $media->getCode(),
//                $event->getContext()['liip'] ?? [],
//                $event->getContext()['callbackUrl'] ?? null
//            )
//        );
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
