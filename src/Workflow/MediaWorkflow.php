<?php

namespace App\Workflow;

use App\Entity\Media;
use App\Message\DownloadImage;
use Survos\WorkflowBundle\Attribute\Workflow;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;

#[Workflow(supports: [Media::class], name: self::WORKFLOW_NAME)]
class MediaWorkflow implements IMediaWorkflow
{
	public const WORKFLOW_NAME = 'MediaWorkflow';

	public function __construct(
        private MessageBusInterface $messageBus,
    )
	{
	}


	#[AsGuardListener(self::WORKFLOW_NAME)]
	public function onGuard(GuardEvent $event): void
	{
		/** @var Media media */
		$media = $event->getSubject();

		switch ($event->getTransition()->getName()) {
		/*
		e.g.
		if ($event->getSubject()->cannotTransition()) {
		  $event->setBlocked(true, "reason");
		}
		App\Entity\Media
		*/
		    case self::TRANSITION_DOWNLOAD:
		        break;
		    case self::TRANSITION_RESIZE:
		        break;
		}
	}


	#[AsTransitionListener(self::WORKFLOW_NAME)]
	public function onTransition(TransitionEvent $event): void
	{
		/** @var Media media */
		$media = $event->getSubject();

		switch ($event->getTransition()->getName()) {
		/*
		e.g.
		if ($event->getSubject()->cannotTransition()) {
		  $event->setBlocked(true, "reason");
		}
		App\Entity\Media
		*/
		    case self::TRANSITION_DOWNLOAD:
                // depending on the marking/filter status, dispatch
                dump($event);
                $envelope = $this->messageBus->dispatch(
                    $msg = new DownloadImage($media->getOriginalUrl(),
                    $media->getRoot(),
                    $media->getCode(),
                    $event->getContext()['liip']??[],
                    $event->getContext()['callbackUrl']??null
            )
            );
                // we _could_ dispatch resize requests here if it's already downloaded

                break;
		    case self::TRANSITION_RESIZE:
		        break;
		}
	}
}
