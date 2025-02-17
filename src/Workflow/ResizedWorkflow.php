<?php

namespace App\Workflow;

use App\Entity\Media;
use App\Entity\Resized;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\Helper\PathHelper;
use Liip\ImagineBundle\Service\FilterService;
use Psr\Log\LoggerInterface;
use Survos\ThumbHashBundle\Service\ThumbHashService;
use Survos\WorkflowBundle\Attribute\Workflow;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Thumbhash\Thumbhash;

#[Workflow(supports: [Resized::class], name: self::WORKFLOW_NAME)]
class ResizedWorkflow implements IResizedWorkflow
{
	public const WORKFLOW_NAME = 'ResizedWorkflow';

	public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('@liip_imagine.service.filter')] private readonly FilterService          $filterService,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,

    )
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
        $media = $resized->getMedia();
//        dd($resized, $resized->getMedia()->getPath());

        // the logic from filterAction
        $path = $media->getPath();
        if (!$path) {
            dd($media->getPath(), $media);
        }
        $path = PathHelper::urlPathToFilePath($path);

        $filter = $resized->getLiipCode();

        // this actually _does_ the image creation and returns the url
        $url =  $this->filterService->getUrlOfFilteredImage(
            $path,
            $filter,
            null,
            true
        );
        $this->logger->info(sprintf('%s (%s) has been resolved to %s',
            $path, $filter, $url));

        // update the info in the database?  Seems like the wrong place to do this.
        // although this is slow, it's nice to know the generated size.
        $cachedUrl =  $this->filterService->getUrlOfFilteredImage(
            $path,
            $filter,
            null,
            true
        );
        // $url _might_ be /resolve?
        $resized->setUrl($cachedUrl);

        // we probably have this locally, but this will also work if the thumbnails are remote
        $request = $this->httpClient->request('GET', $cachedUrl);
        $headers = $request->getHeaders();
        $content = $request->getContent();
        /** @var Media $media */
        $size = (int)$headers['content-length'][0];
        $media->addFilter($filter, $size, $url);
        $service = new ThumbHashService();
        $image = new \Imagick();
        $image->readImageBlob($content);
//        dd($image->getSize()); // rows, columns
        $resized
            ->setSize(strlen($content))
            ->setW($image->getImageWidth())
            ->setH($image->getImageHeight());
//        dd($image->getImageSignature());

        list($width, $height, $pixels) = $service->extract_size_and_pixels_with_imagick($content);

        if ($filter == 'tiny') {
            $hash = Thumbhash::RGBAToHash($width, $height, $pixels);
            $key = Thumbhash::convertHashToString($hash); // You can store this in your database as a string
            $media
                ->setBlurData($hash) // convertStringToHash is better!
                ->setBlur($key);
//            $url = Thumbhash::toDataURL($hash); // use in twig

        }
        $this->entityManager->flush();	}
}
