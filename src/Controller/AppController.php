<?php

namespace App\Controller;

use App\Message\DownloadImage;
use App\Message\ResizeImageMessage;
use App\Repository\MediaRepository;
use App\Service\AppService;
use Survos\ImageClientBundle\Service\ImageClientService;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{

    public function __construct(
        private MessageBusInterface $messageBus,
        private AppService $appService,
        private MediaRepository $mediaRepository,
    )
    {
    }

    #[Route('/', name: 'app_homepage')]
    #[Template('homepage.html.twig')]
    public function index(): array
    {
        return [];
    }

    #[Route('/test-dispatch', name: 'app_test_dispatch')]
    public function testDispatch(
        ImageClientService $imageClientService,
    ): array
    {
        $data = json_decode(file_get_contents('https://dummyjson.com/products'));
        foreach ($data->products as $product) {
            foreach ($product->images as $image) {
                $images[] = $image;
            }
            $imageClientService->dispatchProcess($images, [
                'small'
            ]);
            dd($images);
        }
        return [];
    }

    // https://insight.symfony.com/docs/notifications/custom-webhook.html
    // https://medium.com/@skowron.dev/discovering-symfonys-secret-weapon-the-ultimate-guide-to-the-webhook-component-bae1449f4504
// https://dev.to/sensiolabs/how-to-use-the-new-symfony-maker-command-to-work-with-github-webhooks-2c8n
    #[Route('/test-webhook', name: 'app_webhook')]
    public function webhook(Request $request): Response
    {
        return new Response(json_encode($request->request->all(), JSON_PRETTY_PRINT+ JSON_UNESCAPED_SLASHES));
    }

    #[Route('/dispatch_process/', name: 'app_dispatch_process')]
    public function dispatchProcess(
        #[MapQueryParameter] array $urls=[],
        #[MapQueryParameter] ?string $callbackUrl=null,
    ): JsonResponse
    {
        foreach ($urls as $url) {
            $codes[] = ImageClientService::calculateCode(url: $url);
            $this->messageBus->dispatch(new DownloadImage($url, $callbackUrl));
        }
        $listing = $this->mediaRepository->findBy(['code' => $codes]);
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
        if ($request->getMethod() === Request::METHOD_POST) {
            $urls = $request->request->get('urls', []);
        }

        if (!$media = $this->appService->getMedia($url, $path)) {
            throw new NotFoundHttpException("$url nor $path found");
        }
        $this->messageBus->dispatch(
            new ResizeImageMessage($filter, $media->getPath(), $callbackUrl)
        );

        return $this->json([]);
    }
}
