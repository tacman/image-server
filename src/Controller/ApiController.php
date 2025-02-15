<?php

namespace App\Controller;

use App\Entity\Media;
use App\Form\ProcessPayloadType;
use App\Message\DownloadImage;
use App\Message\ResizeImageMessage;
use App\Repository\MediaRepository;
use App\Service\ApiService;
use Doctrine\ORM\EntityManagerInterface;
use Survos\SaisBundle\Model\ProcessPayload;
use Survos\SaisBundle\Service\SaisClientService;
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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ApiController extends AbstractController implements TokenAuthenticatedController
{

    public function __construct(
        private MessageBusInterface $messageBus,
        private MediaRepository     $mediaRepository,
        private EntityManagerInterface $entityManager,
    )
    {
    }

    // this is in the calling application, here for testing only
    #[Route('/handle_image_resize', name: 'handle_image_resize')]
    public function handleResizeImage(Request $request): Response
    {
        dd();
        return $this->json(['status' => 'ok']);
    }

    #[Route('/ui/dispatch_process', name: 'app_dispatch_process_ui', methods: ['POST', 'GET'])]
    #[Template('test-dispatch.html.twig')]
    public function testDispatch(
        UrlGeneratorInterface $urlGenerator,
        Request $request
    ): Response|array
    {
        $callbackUrl = $urlGenerator->generate('handle_image_resize', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $processPayload = new ProcessPayload(
            'test',
            [
            'https://cdn.dummyjson.com/products/images/beauty/Powder%20Canister/1.png',
            'https://cdn.dummyjson.com/products/images/beauty/Red%20Nail%20Polish/1.png'
        ], [
            'tiny','small', 'medium'
        ], $callbackUrl);
        $form = $this->createForm(ProcessPayloadType::class, $processPayload);
        $form->handleRequest($request);
        // @todo: validate the API key
        if ($form->isSubmitted() && $form->isValid()) {
            // get the payload
            $payload = $form->getData();
            $response = $this->dispatchProcess($payload);
            $results = json_decode($response->getContent());
        }

        return [
            'form' => $form->createView(),
            'results' => $results??[]
        ];
    }

    #[Route('/dispatch_process.{_format}', name: 'app_dispatch_process', methods: ['POST'])]
    public function dispatchProcess(
        #[MapRequestPayload] ProcessPayload $payload,
        string $_format='json'
    ): JsonResponse
    {
        $codes = [];
        foreach ($payload->images as $url) {

            $code = SaisClientService::calculateCode(url: $url);
            if (!$media = $this->mediaRepository->findOneBy(
                ['code' => $code, 'root' => $payload->root]
            )) {
                $media = new Media(root: $payload->root, code: $code, originalUrl: $url);
                $this->entityManager->persist($media);
            }
            $codes[] = $code;
            // or maybe an array?
            $response[] = [
                'code' => $code,
                'url' => $url
                ];
        }
        $this->entityManager->flush();

        // maybe do the filters here instead of download?

        $listing = $this->mediaRepository->findBy(['code' => $codes]);
        foreach ($listing as $media) {
            // depending on the marking/filter status, dispatch
            $envelope = $this->messageBus->dispatch(new DownloadImage($url,
                $media->getRoot(),
                $media->getCode(),
                $payload->filters,
                $payload->callbackUrl));
        }

        return $this->json($listing);
    }

    #[Route('/request/{filter}', name: 'app_request_filter')]
    #[Template('homepage.html.twig')]
    public function requestResizedImage(
        Request $request,
        string $filter,
        #[MapQueryParameter] ?string $url=null,
        #[MapQueryParameter] ?string $path=null,
        #[MapQueryParameter] ?string $callbackUrl=null
    ): JsonResponse
    {
        assert(false, "@todo: payload");
        if ($request->getMethod() === Request::METHOD_POST) {
            $urls = $request->request->get('urls', []);
        }

        if (!$media = $this->apiService->getMedia($url, $path)) {
            throw new NotFoundHttpException("$url nor $path found");
        }
        $this->messageBus->dispatch(
            new ResizeImageMessage($filter, $media->getPath(), $callbackUrl)
        );
        return $this->json($media);
    }
}
