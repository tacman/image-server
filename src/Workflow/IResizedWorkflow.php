<?php

namespace App\Workflow;

use Survos\WorkflowBundle\Attribute\Place;
use Survos\WorkflowBundle\Attribute\Transition;

interface IResizedWorkflow
{
	public const WORKFLOW_NAME = 'ThumbWorkflow';

	#[Place(initial: true)]
	public const PLACE_NEW = 'new';

	#[Place]
	public const PLACE_DONE = 'done';

	#[Transition(from: [self::PLACE_NEW, self::PLACE_DONE], to: self::PLACE_DONE)]
	public const TRANSITION_RESIZE = 'resize';
}
