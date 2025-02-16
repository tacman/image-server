<?php

namespace App\Workflow;

use App\Entity\Resized;
use Survos\WorkflowBundle\Attribute\Workflow;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;

#[Workflow(supports: [Resized::class], name: self::WORKFLOW_NAME)]
class ResizedWorkflow implements IResizedWorkflow
{
	public const WORKFLOW_NAME = 'ResizedWorkflow';

	public function __construct()
	{
	}


	#[AsGuardListener(self::WORKFLOW_NAME)]
	public function onGuard(GuardEvent $event): void
	{
		/** @var Resized resized */
		$resized = $event->getSubject();

		switch ($event->getTransition()->getName()) {
		    case self::TRANSITION_RESIZE:
                // @check if it's already done (if it's marked as resize)
//                if ($resized->getSize()) {
//                    $event->setBlocked(true, "reason");
//                }
		        break;
		}
	}


	#[AsTransitionListener(self::WORKFLOW_NAME, self::TRANSITION_RESIZE)]
	public function onTransition(TransitionEvent $event): void
	{
		/** @var Resized resized */
		$resized = $event->getSubject();

        // we don't need to re-dispatch, it can be here.
	}
}
