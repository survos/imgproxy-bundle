<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle\Command;

use Survos\ImgproxyBundle\Service\ImgproxyUrlBuilder;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('imgproxy:url', 'Generate a signed imgproxy URL for a source image')]
final class ImgproxyUrlCommand
{
    public function __construct(private readonly ImgproxyUrlBuilder $imgproxyUrlBuilder) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Source image URL')] string $url,
        #[Argument('Preset name')] string $preset = 'ai',
    ): int {
        if (!$this->imgproxyUrlBuilder->hasPreset($preset)) {
            $io->error(sprintf(
                'Unknown preset "%s". Available: %s',
                $preset,
                implode(', ', array_keys($this->imgproxyUrlBuilder->getPresets())),
            ));
            return Command::FAILURE;
        }

        $result = $this->imgproxyUrlBuilder->resizePreset($url, $preset);

        $io->writeln(sprintf('<href=%s>%s</>', $result, $result));

        return Command::SUCCESS;
    }
}
