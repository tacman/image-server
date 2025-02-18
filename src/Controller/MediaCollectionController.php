<?php

// uses Survos Param Converter, from the UniqueIdentifiers method of the entity.

namespace App\Controller;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use App\Entity\Media;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Survos\ApiGrid\Components\ApiGridComponent;
use Survos\WorkflowBundle\Traits\HandleTransitionsTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/media')]
class MediaCollectionController extends AbstractController
{
    use HandleTransitionsTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ApiGridComponent $apiGridComponent,
        private ?IriConverterInterface $iriConverter = null
    ) {
    }

    #[Route(path: '/browse/', name: 'media_browse', methods: ['GET'])]
    #[Route('/index', name: 'media_index', options: ['description' => "Browse with database"])]
    public function browse_media(Request $request): Response
    {
        $class = Media::class;
        $shortClass = 'Media';
        $useMeili = 'app_browse' == $request->get('_route');
        // this should be from inspection bundle!
        $apiCall = $useMeili
        ? '/api/meili/'.$shortClass
        : $this->iriConverter->getIriFromResource($class, operation: new GetCollection(),
            context: $context ?? [])
        ;

        $this->apiGridComponent->setClass($class);
        $c = $this->apiGridComponent->getDefaultColumns();
        $columns = array_values($c);
        $useMeili = 'media_browse' == $request->get('_route');
        // this should be from inspection bundle!
        $apiCall = $useMeili
        ? '/api/meili/'.$shortClass
        : $this->iriConverter->getIriFromResource($class, operation: new GetCollection(),
            context: $context ?? [])
        ;

        return $this->render('browse/media.html.twig', [
            'class' => $class,
            'useMeili' => $useMeili,
            'apiCall' => $apiCall,
            'columns' => $columns,
            'filter' => [],
        ]);
    }

    #[Route('/symfony_crud_index', name: 'media_symfony_crud_index')]
    public function symfony_crud_index(MediaRepository $mediaRepository): Response
    {
        return $this->render('media/index.html.twig', [
            'medias' => $mediaRepository->findBy([], [], 30),
        ]);
    }


}
