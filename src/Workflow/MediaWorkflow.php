<?php

namespace App\Workflow;

use App\Entity\Media;
use Survos\WorkflowBundle\Attribute\Workflow;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;


// See events at https://symfony.com/doc/current/workflow.html#using-events

// @todo: add the entity class to attach this to.
#[Workflow(supports: [Media::class], name: self::WORKFLOW_NAME)]
final class MediaWorkflow implements IMediaWorkflow
{

    public function __construct(
        // add services
    )
    {
    }

    #[AsGuardListener(self::WORKFLOW_NAME)]
    public function onGuard(GuardEvent $event): void
    {
        // switch ($event->getTransition()) { ...
    }

    #[AsTransitionListener(self::WORKFLOW_NAME)]
    public function onTransition(TransitionEvent $event): void
    {
        switch ($event->getTransition()->getName()) {
            case self::TRANSITION_DOWNLOAD:
                break;
            case self::TRANSITION_RESIZE:
                break;
        }
    }

}
