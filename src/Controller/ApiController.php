<?php

namespace App\Controller;

use App\Entity\Media;
use App\Form\ProcessPayloadType;
use App\Message\DownloadImage;
use App\Message\ResizeImageMessage;
use App\Message\SendWebhookMessage;
use App\Repository\MediaRepository;
use App\Service\ApiService;
use App\Workflow\IMediaWorkflow;
use App\Workflow\MediaWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Survos\SaisBundle\Message\MediaUploadMessage;
use Survos\SaisBundle\Model\ProcessPayload;
use Survos\SaisBundle\Model\ThumbPayload;
use Survos\SaisBundle\Service\SaisClientService;
use Survos\WorkflowBundle\Message\AsyncTransitionMessage;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ApiController extends AbstractController implements TokenAuthenticatedController
{

    public function __construct(
        private MessageBusInterface          $messageBus,
        private MediaRepository              $mediaRepository,
        private EntityManagerInterface       $entityManager,
        private NormalizerInterface          $normalizer,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger,
    )
    {
    }

    // this is in the calling application, here for testing only
    #[Route('/handle_image_resize', name: 'handle_image_resize')]
    #[Route('/handle_media', name: 'handle_media')]
    public function handleResizeImage(
        Request $request,
//        #[MapRequestPayload] ?ThumbPayload $thumbPayload=null,
    ): Response
    {
        return $this->json(['status' => 'ok']);
        return $this->json($thumbPayload);
    }

    #[Route('/ui/dispatch_process', name: 'app_dispatch_process_ui', methods: ['POST', 'GET'])]
    #[Template('test-dispatch.html.twig')]
    public function testDispatch(
        UrlGeneratorInterface $urlGenerator,
        Request               $request
    ): Response|array
    {
        $thumbCallback = $urlGenerator->generate('handle_image_resize', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $mediaCallback = $urlGenerator->generate('handle_media', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $processPayload = new ProcessPayload(
            'test',
            [
                'https://asset.museum-digital.org/rlp/images/201212/14120104912.jpg',
//            'https://cdn.dummyjson.com/products/images/beauty/Red%20Nail%20Polish/1.png'
            ], [
            'tiny',
//                'small', 'medium'
        ], mediaCallbackUrl: $mediaCallback,
            thumbCallbackUrl: $thumbCallback,
        );
        $form = $this->createForm(ProcessPayloadType::class, $processPayload);
        $form->handleRequest($request);
        // @todo: validate the API key
        if ($form->isSubmitted() && $form->isValid()) {
            // get the payload
            /** @var ProcessPayload $payload */
            $payload = $form->getData();
            $response = $this->dispatchProcess($payload);
            $results = json_decode($response->getContent());
        }

        return [
            'form' => $form->createView(),
            'results' => $results ?? []
        ];
    }

    /**
     * When a request comes in, populate the media database and return what we know of media.
     * Dispatch download.
     * After download, dispatch resize
     * @param ProcessPayload $payload
     * @param string $_format
     * @return JsonResponse
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @todo: handle tasks, which should be batched and recorded
     *
     */
    #[Route('/dispatch_process.{_format}', name: 'app_dispatch_process', methods: ['POST'])]
    public function dispatchProcess(
        #[MapRequestPayload] ProcessPayload $payload,
        string                              $_format = 'json'
    ): JsonResponse
    {
        $codes = [];
        foreach ($payload->images as $url) {

            $code = SaisClientService::calculateCode($url, $payload->root);
            if (!$media = $this->mediaRepository->findOneBy(
                ['code' => $code, 'root' => $payload->root]
            )) {
                $media = new Media(root: $payload->root, code: $code, originalUrl: $url);
                $this->entityManager->persist($media);
            }
            // add the filters so we have them for after download.
            $filters = $media->getThumbData();
            foreach ($payload->filters as $filter) {
                if (!array_key_exists($filter, $filters)) {
                    $filters[$filter] = [];
                }
            }
            $media->setThumbData($filters);
            $codes[] = $code;
        }
        $this->entityManager->flush();

        // maybe do the filters here instead of download?

        $listing = $this->mediaRepository->findBy(['code' => $codes]);
        foreach ($listing as $media) {
            // instead of dispatching directly here, dispatch a transition
            $envelope = $this->messageBus->dispatch(new AsyncTransitionMessage(
                $media->getCode(),
                Media::class,
                IMediaWorkflow::TRANSITION_DOWNLOAD,
                workflow: MediaWorkflow::WORKFLOW_NAME,
                context: [
                    'liip' => $payload->filters,
                    'mediaCallbackUrl' => $payload->mediaCallbackUrl,
                    'thumbCallbackUrl' => $payload->thumbCallbackUrl,
                ]
            ), [
//                new TransportNamesStamp('sync')
                new TransportNamesStamp('download')
            ]);

        }

        $response = $this->normalizer->normalize($listing, 'object', ['groups' => ['media.read', 'marking']]);
        foreach ($response as $mediaData) {
            $envelope = $this->messageBus->dispatch(new SendWebhookMessage($payload->mediaCallbackUrl, (object)$mediaData));
        }

        return $this->json($response);
    }
}
