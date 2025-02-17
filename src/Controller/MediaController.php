<?php


// uses Survos Param Converter, from the UniqueIdentifiers method of the entity.

namespace App\Controller;

use App\Entity\Media;
use App\Form\MediaType;
use App\Workflow\IMediaWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\MediaRepository;
use Survos\WorkflowBundle\Traits\HandleTransitionsTrait;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Workflow\WorkflowInterface;

// if Workflow Bundle active
// use Survos\WorkflowBundle\Traits\HandleTransitionsTrait;

#[Route('/media/{mediaId}')]
class MediaController extends AbstractController
{
    use HandleTransitionsTrait;

    public function __construct(private EntityManagerInterface $entityManager)
    {

    }

// there must be a way to do this within the bundle, a separate route!
    #[Route(path: '/transition/{transition}', name: 'medium_transition')]
    public function transition(Request                                                    $request,
                               #[Target(IMediaWorkflow::WORKFLOW_NAME)] WorkflowInterface $workflow,
                               string                                                     $transition,
                               Media $media): Response
    {
        if ($transition == '_') {
            $transition = $request->request->get('transition'); // the _ is a hack to display the form, @todo: cleanup
        }

        $this->handleTransitionButtons($workflow, $transition, $media);
        $this->entityManager->flush(); // to save the marking
        return $this->redirectToRoute('medium_show', $media->getRP());
    }

    #[Route('/', name: 'media_show', options: ['expose' => true])]
    public function show(Media $media): Response
    {
        return $this->render('media/show.html.twig', [
            'medium' => $media,
        ]);
    }

}
