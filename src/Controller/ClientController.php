<?php

namespace App\Controller;

use App\Repository\MediaRepository;
use Survos\SaisBundle\Model\ProcessPayload;
use Survos\SaisBundle\Service\SaisClientService;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ClientController extends AbstractController
{

    #[Route('/status', name: 'app_status')]
    public function status(): array|JsonResponse
    {
        return $this->json([
            'status' => 'okay'
        ]);
    }

    #[Route('/', name: 'app_homepage')]
    #[Template('homepage.html.twig')]
    public function index(MediaRepository $mediaRepository): array
    {
        return ['rows' => $mediaRepository->findBy([], ['path' => 'DESC'], 30)];
    }

    #[Route('/test-dispatch', name: 'app_test_dispatch')]
    #[Template('test-dispatch.html.twig')]
    public function testDispatch(
        SaisClientService $saisService,
    ): array|Response
    {
        $response  = $saisService->fetch('/status');
        dd($response);
        $data = json_decode(file_get_contents('https://'));
        $data = json_decode(file_get_contents('https://dummyjson.com/products'));
        $results = [];
        foreach ($data->products as $product) {
            foreach ($product->images as $image) {
                $images[] = $image;
            }
            $payload = new ProcessPayload($images, ['small']);
            $results[] =
                [
                    'payload' => $payload,
                    'response' => $saisService->dispatchProcess($payload)
                ];
        }
        return $results;

    }

    // https://insight.symfony.com/docs/notifications/custom-webhook.html
    // https://medium.com/@skowron.dev/discovering-symfonys-secret-weapon-the-ultimate-guide-to-the-webhook-component-bae1449f4504
// https://dev.to/sensiolabs/how-to-use-the-new-symfony-maker-command-to-work-with-github-webhooks-2c8n
    #[Route('/test-webhook', name: 'app_webhook')]
    public function webhook(Request $request): Response
    {
        return new Response(json_encode($request->request->all(), JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES));
    }

}
