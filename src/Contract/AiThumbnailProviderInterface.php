<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle\Contract;

interface AiThumbnailProviderInterface
{
    /**
     * Return a URL suitable for AI vision tasks: ~512px, JPEG.
     * Return the full-resolution URL if no small variant is available.
     */
    public function getAiSmallUrl(): string;
}
