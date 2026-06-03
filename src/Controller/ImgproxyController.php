<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle\Controller;

use Survos\ImgproxyBundle\Service\ImgproxyUrlBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class ImgproxyController extends AbstractController
{
    public function __construct(
        private readonly ImgproxyUrlBuilder $imgproxyUrlBuilder,
    ) {
    }

    #[Route('/imgproxy/url', name: 'survos_imgproxy_url', methods: ['GET'])]
    public function url(
        #[MapQueryParameter] string $url,
        #[MapQueryParameter] string $preset = 'observe',
    ): JsonResponse {
        return $this->json([
            'url' => $this->imgproxyUrlBuilder->resizePreset($url, $preset),
        ]);
    }

    /**
     * Fetch image metadata from imgproxy's PRO /info endpoint.
     *
     *   GET /imgproxy/info?url=…&opts[]=dimensions&opts[]=classify_objects:5
     *
     * @param list<string> $opts
     */
    #[Route('/imgproxy/info', name: 'survos_imgproxy_info', methods: ['GET'])]
    public function info(
        #[MapQueryParameter] string $url,
        #[MapQueryParameter] array $opts = [],
    ): JsonResponse {
        return $this->json($this->imgproxyUrlBuilder->info($url, $opts ?: ['size', 'format', 'dimensions']));
    }
}
