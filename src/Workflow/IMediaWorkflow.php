<?php

namespace App\Workflow;

use Survos\WorkflowBundle\Attribute\Place;
use Survos\WorkflowBundle\Attribute\Transition;

interface IMediaWorkflow
{
	public const WORKFLOW_NAME = 'MediaWorkflow';

	#[Place(initial: true)]
	public const PLACE_NEW = 'new';

	#[Place]
	public const PLACE_DOWNLOADED = 'downloaded';
	public const PLACE_FAILED = 'failed';

	#[Place]
	public const PLACE_RESIZED = 'resized';

	#[Transition(from: [self::PLACE_NEW, self::PLACE_DOWNLOADED, self::PLACE_FAILED], to: self::PLACE_DOWNLOADED)]
	public const TRANSITION_DOWNLOAD = 'download';

    #[Transition(from: [self::PLACE_DOWNLOADED], to: self::PLACE_FAILED)]
    public const TRANSITION_DOWNLOAD_FAILED = 'download_failed';

	#[Transition(from: [self::PLACE_DOWNLOADED, self::PLACE_RESIZED], to: self::PLACE_RESIZED)]
	public const TRANSITION_RESIZE = 'resize';
}
