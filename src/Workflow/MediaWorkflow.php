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
        private readonly LoggerInterface                          $logger,
        private readonly HttpClientInterface                      $httpClient,
        private readonly ApiService                               $apiService,
        private readonly MediaRepository                          $mediaRepository,
        #[Target(ThumbWorkflowInterface::WORKFLOW_NAME)] private WorkflowInterface $thumbWorkflow,
        #[Target(IMediaWorkflow::WORKFLOW_NAME)] private WorkflowInterface $mediaWorkflow,

        #[Autowire('@liip_imagine.service.filter')]
        private readonly FilterService                            $filterService,
        private SerializerInterface                               $serializer,
        private NormalizerInterface                               $normalizer,
        #[Autowire('%env(SAIS_API_ENDPOINT)%')] private string    $apiEndpoint,
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
        if ($media->getStatusCode() !== 200) {
            $this->mediaWorkflow->apply($media, IMediaWorkflow::TRANSITION_DOWNLOAD_FAILED);
        } else {
            $this->resizeMedia($media, ['tiny', 'small', 'medium', 'large']);
        }
        $this->entityManager->flush();
//        $event->getContext()['liipCodes']);

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
        if ($media->getStatusCode() !== 200) {
            return;
        }
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

    /**
     * @throws FilesystemException
     * @throws TransportExceptionInterface
     */
    #[AsTransitionListener(self::WORKFLOW_NAME, IMediaWorkflow::TRANSITION_DOWNLOAD)]
    public function onDownload(TransitionEvent $event): void
    {
        /** @var Media media */
        $media = $this->getMedia($event);
        $url = $media->getOriginalUrl();
//        $url = 'https://ciim-public-media-s3.s3.eu-west-2.amazonaws.com/ramm/41_2005_3_2.jpg';
//        $url = 'https://coleccion.museolarco.org/public/uploads/ML038975/ML038975a_1733785969.webp';
//        $media->setOriginalUrl($url);
        // we use the original extension

        $path = $media->getRoot() . '/' . SaisClientService::calculatePath($media->getCode());
        $tempFile = tempnam("/tmp", $path);
//        $tempFile = $media->getCode() . '.' . pathinfo($url, PATHINFO_EXTENSION);// no dirs

        $media->setStatusCode(200);
//        dd($tempFile);
        // if we have size, we've already downloaded the important data.
        if (!$media->getSize())
        {
            try {
                $this->downloadUrl($url, $tempFile);
                $this->processTempFile($tempFile, $media);
            } catch (\Exception $e) {
                $media->setStatusCode($e->getCode());
                return;
            }
        }
        $uri = parse_url($url, PHP_URL_PATH);
        $ext = pathinfo($uri, PATHINFO_EXTENSION);
//        dd($ext, $url, $uri);
        // if there's no ext, it's a lot more work to get it from the image itself!
        assert($ext, "@todo: handle missing extension " . $media->getOriginalUrl());

        // upload it to long-term storage
        if (!$this->defaultStorage->has($path)) {
            try {
                $this->uploadUrl($tempFile, $path);
                $this->logger->warning(sprintf('Upload url: %s', $path));
            } catch (FilesystemException|UnableToWriteFile $exception) {
                // handle the error
                $this->logger->error($exception->getMessage());
                return; // transition?
            }

            $this->logger->info("$url downloaded to " . $path);
        } else {
            $this->logger->info("$url already exists as  $path");
        }

        $media
            ->setPath($path)
            ->setOriginalUrl($url)
            ;
        // we're done, so delete the temp file
        unlink($tempFile); // so it doesn't fill up the disk



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

    private function processTempFile(string $tempFile, Media $media): void
    {
        $content = file_get_contents($tempFile);
        $mimeType = mime_content_type($tempFile);
//        $size = getimagesize($media->getOriginalUrl(), $info);
        [$width, $height, $type, $attr]  = getimagesize($tempFile, $info);
//        dd($exif, $height, $width, $type, $attr, $tempFile);

        // free, but inconsistent sizes.
//        if (exif_thumbnail($tempFile, $width, $height, $type)) {
//            dd($type, $height, $width, $mimeType, $media->getOriginalUrl());
//        } else {
//            // maybe it's a PDF?
//        }
//        dd(filesize($tempFile));

        // maybe someday: https://github.com/brianmcdo/ImagePalette?tab=readme-ov-file
        $media
            ->setOriginalWidth($width)
            ->setOriginalHeight($height)
            ->setMimeType($mimeType) // the actual mime type
            ->setSize(filesize($tempFile));
//        dd($media, $exif, $mimeType, $width, $height);

//        {"message":"Error thrown while handling message Survos\\WorkflowBundle\\Message\\AsyncTransitionMessage. Removing from transport after 3 retries. Error: \"Handling \"Survos\\WorkflowBundle\\Message\\AsyncTransitionMessage\" failed: An exception occurred while executing a query: SQLSTATE[22P05]: Untranslatable character: 7 ERROR:  unsupported Unicode escape sequence\nDETAIL:  \\u0000 cannot be converted to text.\nCONTEXT:  JSON data, line 1: ...h\":16,\"FocalLength\":\"58\\/1\",\"UserComment\":\"\\u0000...\nunnamed portal parameter $6 = '...'\"","context":{"class":"Survos\\WorkflowBundle\\Message\\AsyncTransitionMessage","message_id":null,"retryCount":3,"error":"Handling \"Survos\\WorkflowBundle\\Message\\AsyncTransitionMessage\" failed: An exception occurred while executing a query: SQLSTATE[22P05]: Untranslatable character: 7 ERROR:  unsupported Unicode escape sequence\nDETAIL:  \\u0000 cannot be converted to text.\nCONTEXT:  JSON data, line 1: ...h\":16,\"FocalLength\":\"58\\/1\",\"UserComment\":\"\\u0000...\nunnamed portal parameter $6 = '...'","exception":{"class":"Symfony\\Component\\Messenger\\Exception\\HandlerFailedException","message":"Handling \"Survos\\WorkflowBundle\\Message\\AsyncTransitionMessage\" failed: An exception occurred while executing a query: SQLSTATE[22P05]: Untranslatable character: 7 ERROR:  unsupported Unicode escape sequence\nDETAIL:  \\u0000 cannot be converted to text.\nCONTEXT:  JSON data, line 1: ...h\":16,\"FocalLength\":\"58\\/1\",\"UserComment\":\"\\u0000...\nunnamed portal parameter $6 = '...'","code":7,"file":"/app/vendor/symfony/messenger/Middleware/HandleMessageMiddleware.php:124","previous":{"class":"Doctrine\\DBAL\\Exception\\DriverException","message":"An exception occurred while executing a query: SQLSTATE[22P05]: Untranslatable character: 7 ERROR:  unsupported Unicode escape sequence\nDETAIL:  \\u0000 cannot be converted to text.\nCONTEXT:  JSON data, line 1: ...h\":16,\"FocalLength\":\"58\\/1\",\"UserComment\":\"\\u0000...\nunnamed portal parameter $6 = '...'","code":7,"file":"/app/vendor/doctrine/dbal/src/Driver/API/PostgreSQL/ExceptionConverter.php:80","previous":{"class":"Doctrine\\DBAL\\Driver\\PDO\\Exception","message":"SQLSTATE[22P05]: Untranslatable character: 7 ERROR:  unsupported Unicode escape sequence\nDETAIL:  \\u0000 cannot be converted to text.\nCONTEXT:  JSON data, line 1: ...h\":16,\"FocalLength\":\"58\\/1\",\"UserComment\":\"\\u0000...\nunnamed portal parameter $6 = '...'","code":7,"file":"/app/vendor/doctrine/dbal/src/Driver/PDO/Exception.php:24","previous":{"class":"PDOException","message":"SQLSTATE[22P05]: Untranslatable character: 7 ERROR:  unsupported Unicode escape sequence\nDETAIL:  \\u0000 cannot be converted to text.\nCONTEXT:  JSON data, line 1: ...h\":16,\"FocalLength\":\"58\\/1\",\"UserComment\":\"\\u0000...\nunnamed portal para

        // problems encoding exif
        if (0) {
            $exif = exif_read_data($tempFile);
            $media->setExif($exif);
        }

    }


    /**
     * @param string $tempFile
     * @param string $code
     * @return void
     * @throws FilesystemException
     */
    private function uploadUrl(string $tempFile, string $code): void
    {
        $stream = fopen($tempFile, 'r');
        $config = []; // visibility?
        $directory = pathinfo($code, PATHINFO_DIRNAME);
        if (!$this->defaultStorage->directoryExists($directory)) {
            $this->defaultStorage->createDirectory($directory);
        }
        $this->defaultStorage->writeStream($code, $stream, $config);

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
        $code = $response->getStatusCode();
        if (200 !== $code) {
            throw new \Exception("Problem with $url " . $response->getStatusCode(), code: $code);
        }

        $fileHandler = fopen($tempFile, 'w');
        foreach ($client->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }
        fclose($fileHandler);
        return $tempFile;

    }


}
