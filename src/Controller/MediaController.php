<?php

namespace App\Controller;

use App\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;



class MediaController extends AbstractController
{


    public function __construct(
        private MediaRepository $mediaRepository
    )
    {
    }

    #[Route('/media', name: 'app_media')]
    public function index(): Response
    {
        return $this->render('media/index.html.twig', [
            'medias' => $this->mediaRepository->findBy([], [], 40),
            'controller_name' => 'MediaController',
        ]);
    }
}
