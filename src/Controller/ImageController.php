<?php

namespace App\Controller;

use Imagine\Exception\RuntimeException;
use Liip\ImagineBundle\Config\Controller\ControllerConfig;
use Liip\ImagineBundle\Controller\ImagineController;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Cache\Helper\PathHelper;
use Liip\ImagineBundle\Imagine\Cache\SignerInterface;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ImageController extends ImagineController
{
    public function __construct(
        private FilterService $filterService,
        private DataManager $dataManager,
        private SignerInterface $signer,
        private ?ControllerConfig $controllerConfig = null,
        private ?CacheManager $cacheManager=null,
    ) {
        parent::__construct($this->filterService, $this->dataManager, $this->signer, $this->controllerConfig);

    }

    /**
     * This action applies a given filter -merged with additional runtime filters- to a given image, saves the image and
     * redirects the browser to the stored image.
     *
     * The resulting image is cached so subsequent requests will redirect to the cached image instead applying the
     * filter and storing the image again.
     *
     * @param string $hash
     * @param string $path
     * @param string $filter
     *
     * @throws RuntimeException
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     *
     * @return RedirectResponse
     */
    public function filterRuntimeAction(Request $request, $hash, $path, $filter)
    {
        $resolver = $request->get('resolver');
        $path = PathHelper::urlPathToFilePath($path);
        $result = parent::filterRuntimeAction($request, $hash, $path, $filter);
        dd($result);
        $runtimeConfig = $this->getFiltersBc($request);
    }

        /**
     * This action applies a given filter to a given image, saves the image and redirects the browser to the stored
     * image.
     *
     * The resulting image is cached so subsequent requests will redirect to the cached image instead applying the
     * filter and storing the image again.
     *
     * @param string $path
     * @param string $filter
     *
     * @throws RuntimeException
     * @throws NotFoundHttpException
     *
     * @return RedirectResponse
     */
    public function filterAction(Request $request, $path, $filter)
    {

        $path = PathHelper::urlPathToFilePath($path);
        $resolver = $request->get('resolver');

        // this is the final url, but it also generates the image which is slow.
        $url =  $this->filterService->getUrlOfFilteredImage(
            $path,
            $filter,
            $resolver,
            $this->isWebpSupported($request)
        );

        dd($url);

        $parent = parent::filterAction($request, $path, $filter);
        dd($parent, $path, $resolver);

        return $this->createRedirectResponse(function () use ($path, $filter, $resolver, $request) {
            return $this->filterService->getUrlOfFilteredImage(
                $path,
                $filter,
                $resolver,
                $this->isWebpSupported($request)
            );
        }, $path, $filter);
    }

    private function isWebpSupported(Request $request): bool
    {
        return false !== mb_stripos($request->headers->get('accept', ''), 'image/webp');
    }

}
