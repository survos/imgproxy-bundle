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
        #[MapQueryParameter] string $preset = 'ai_thumbnail',
    ): JsonResponse {
        return $this->json([
            'url' => $this->imgproxyUrlBuilder->resizePreset($url, $preset, 'jpg'),
        ]);
    }
}
