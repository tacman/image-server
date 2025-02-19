<?php

namespace App\Controller;

use App\Repository\MediaRepository;
use App\Repository\ThumbRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;



class BrowseController extends AbstractController
{


    public function __construct(
        private MediaRepository $mediaRepository,
        private ThumbRepository $thumbRepository
    )
    {
    }

    #[Route('/app/media', name: 'app_media')]
    public function index(
        #[MapQueryParameter] ?string $marking=null,
        #[MapQueryParameter] ?string $root=null
    ): Response
    {
        $where = [];
        if ($marking) {
            $where['marking'] = $marking;
        }
        if ($root) {
            $where['root'] = $root;
        }
        return $this->render('browse/media.html.twig', [
            'medias' => $this->mediaRepository->findBy($where, [], 40)
        ]);
    }

    #[Route('/app/thumbs', name: 'app_thumbs')]
    public function thumbs(
        #[MapQueryParameter] ?string $marking=null

    ): Response
    {
        $where = [];
        if ($marking) {
            $where['marking'] = $marking;
        }

        return $this->render('browse/thumbs.html.twig', [
            'rows' => $this->thumbRepository->findBy($where, ['id' => 'DESC'], 40),
            'controller_name' => 'thumbsController',
        ]);
    }
}
