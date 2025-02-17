<?php

namespace App\Controller;

use App\Repository\MediaRepository;
use App\Repository\ThumbRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;



class BrowseController extends AbstractController
{


    public function __construct(
        private MediaRepository $mediaRepository,
        private ThumbRepository $resizedRepository
    )
    {
    }

    #[Route('/app/media', name: 'app_media')]
    public function index(): Response
    {
        return $this->render('browse/media.html.twig', [
            'medias' => $this->mediaRepository->findBy([], [], 40)
        ]);
    }

    #[Route('/app/thumbs', name: 'app_thumbs')]
    public function thumbs(): Response
    {
        return $this->render('browse/thumbs.html.twig', [
            'rows' => $this->thumbsRepository->findBy([], ['id' => 'DESC'], 40),
            'controller_name' => 'thumbsController',
        ]);
    }
}
