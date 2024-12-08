<?php

namespace App\Controller;

use App\Message\DownloadImage;
use App\Message\ResizeImageMessage;
use App\Repository\MediaRepository;
use App\Service\ApiService;
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

class ApiController extends AbstractController
{

    public function __construct(
        private MessageBusInterface $messageBus,
        private ApiService          $apiService,
        private MediaRepository     $mediaRepository,
    )
    {
    }

    #[Route('/dispatch_process/', name: 'app_dispatch_process')]
    public function dispatchProcess(
        #[MapQueryParameter] array $urls=[],
        #[MapQueryParameter] array $filters=[],
        #[MapQueryParameter] ?string $callbackUrl=null,
    ): JsonResponse
    {
        // urls? codes? paths?
        foreach ($urls as $url) {
            $codes[] = ImageClientService::calculateCode(url: $url);
            $this->messageBus->dispatch(new DownloadImage($url,
                $filters,
                $callbackUrl));
        }

        // maybe do the filters here instead of download?

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

        if (!$media = $this->apiService->getMedia($url, $path)) {
            throw new NotFoundHttpException("$url nor $path found");
        }
        $this->messageBus->dispatch(
            new ResizeImageMessage($filter, $media->getPath(), $callbackUrl)
        );
        return $this->json($media);
    }
}
