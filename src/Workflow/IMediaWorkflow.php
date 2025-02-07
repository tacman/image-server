<?php

namespace App\Workflow;

use Survos\WorkflowBundle\Attribute\Transition;

// See events at https://symfony.com/doc/current/workflow.html#using-events

interface IMediaWorkflow
{
    // This name is used for injecting the workflow into a service
    // #[Target(IMediaWorkflow::WORKFLOW_NAME)] private WorkflowInterface $workflow
    public const WORKFLOW_NAME = 'MediaWorkflow';

    public const PLACE_NEW = 'new';
    public const PLACE_DOWNLOADED = 'downloaded';
    public const PLACE_RESIZED = 'resized';

    #[Transition([self::PLACE_NEW], self::PLACE_NEW)]
    public const TRANSITION_DOWNLOAD = 'download';
    #[Transition([self::PLACE_DOWNLOADED], self::PLACE_DOWNLOADED)]
    public const TRANSITION_RESIZE = 'resize';
}
