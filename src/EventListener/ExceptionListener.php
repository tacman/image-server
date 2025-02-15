<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Survos\KeyValueBundle\Entity\KeyValueManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ExceptionListener
{
    public function __construct(
        private KeyValueManagerInterface $keyValueManager,
        private LoggerInterface $logger,
    )
    {

    }
    #[AsEventListener(ExceptionEvent::class, priority: 10000)]
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception::class === NotFoundHttpException::class) {
            $request = $event->getRequest();
            $path = $request->getPathInfo();
            if (!$this->keyValueManager->has($path, 'paths')) {
                $this->logger->warning('Path "{path}" does not exist.', ['path' => $path]);
                $this->keyValueManager->add($path, 'paths');
            }
        }
    }
}
