<?php

namespace App\Controller;

use App\Form\ProcessPayloadType;
use App\Repository\MediaRepository;
use Survos\SaisBundle\Model\ProcessPayload;
use Survos\SaisBundle\Service\SaisClientService;
use Survos\ThumbHashBundle\Service\BlurService;
use Survos\ThumbHashBundle\Service\ThumbHashService;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thumbhash\Thumbhash;

class ClientController extends AbstractController
{

    #[Route('/status', name: 'app_status')]
    public function status(): array|JsonResponse
    {
        return $this->json([
            'status' => 'okay'
        ]);
    }

    /**
     * @throws \ImagickException
     * @throws \ImagickPixelException
     */
    #[Route('/', name: 'app_homepage')]
    #[Template('homepage.html.twig')]
    public function index(MediaRepository $mediaRepository): array
    {


//        $content = file_get_contents('https://cdn.dummyjson.com/products/images/beauty/Essence%20Mascara%20Lash%20Princess/1.png');
//        $content = file_get_contents('https://cdn.dummyjson.com/products/images/beauty/Essence%20Mascara%20Lash%20Princess/thumbnail.png');
//        $content = file_get_contents(__DIR__ . '/../../sunrise.jpg');
//        $filename = __DIR__ . '/../../walter1.jpg';
//
//        list($width, $height, $pixels) = ThumbHashService::extract_size_and_pixels_with_imagick($content);
////        list($width, $height, $pixels) = ThumbHashService::extract_size_and_pixels_with_gd($content);
//
//        try {
//            $hash = Thumbhash::RGBAToHash($width, $height, $pixels);
//            $key = Thumbhash::convertHashToString($hash); // You can store this in your database as a string
//            $url = Thumbhash::toDataURL($hash);
//        } catch (\Exception $e) {
//            dd($e->getMessage());
//        }
//        dd($width, $height, $hash, $key, $url);

        return ['rows' => $mediaRepository->findBy([], ['createdAt' => 'ASC'], 30)];
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
