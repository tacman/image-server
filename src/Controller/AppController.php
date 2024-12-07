<?php

namespace App\Controller;

use App\Message\DownloadImage;
use App\Message\ResizeImageMessage;
use App\Service\AppService;
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
    )
    {
    }

    #[Route('/', name: 'app_homepage')]
    #[Template('homepage.html.twig')]
    public function index(): array
    {
        return [];
    }

    #[Route('/test-webhook', name: 'app_webhook')]
    public function webhook(Request $request): Response
    {
        return new Response(json_encode($request->request->all(), JSON_PRETTY_PRINT+ JSON_UNESCAPED_SLASHES));
    }

    #[Route('/fetch/', name: 'app_fetch')]
    public function fetch(
        #[MapQueryParameter] string $url,
        #[MapQueryParameter] string $callbackUrl,
    ): JsonResponse
    {
        $this->messageBus->dispatch(new DownloadImage($url, $callbackUrl));

        return $this->json([]);
    }

    #[Route('/request/{filter}', name: 'app_request_filter')]
    #[Template('homepage.html.twig')]
    public function requestResizedImage(
        string $filter,
        #[MapQueryParameter] ?string $url=null,
        #[MapQueryParameter] ?string $path=null,
        #[MapQueryParameter] ?string $callbackUrl=null
    ): JsonResponse
    {
        if (!$media = $this->appService->getMedia($url, $path)) {
            throw new NotFoundHttpException("$url nor $path found");
        }
        $this->messageBus->dispatch(
            new ResizeImageMessage($filter, $media->getPath(), $callbackUrl)
        );

        return $this->json([]);
    }
}
