<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle\Command;

use Survos\ImgproxyBundle\Service\ImgproxyUrlBuilder;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('imgproxy:info', 'Fetch image metadata from imgproxy\'s PRO /info endpoint')]
final class ImgproxyInfoCommand
{
    /** Sample IIIF image used when no URL is supplied. */
    private const SAMPLE_URL = 'https://sammlung.belvedere.at/internal/media/downloaddispatcher/13323?download=/full/max/0/default.jpg';

    public function __construct(private readonly ImgproxyUrlBuilder $imgproxyUrlBuilder) {}

    /**
     * @param list<string> $opt
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Source image URL (http(s):// or s3://bucket/key); defaults to a sample image')] string $url = self::SAMPLE_URL,
        #[Option('Info option token, repeatable (e.g. dimensions, blurhash:4:3, classify_objects:5)', name: 'opt')] array $opt = [],
        #[Option('Print the raw JSON response instead of a table')] bool $json = false,
    ): int {
        $options = $opt ?: ['size', 'format', 'dimensions'];

        $infoUrl = $this->imgproxyUrlBuilder->infoUrl($url, $options);
        $io->writeln(sprintf('<href=%s>%s</>', $infoUrl, $infoUrl));

        try {
            $data = $this->imgproxyUrlBuilder->info($url, $options);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if ($json) {
            $io->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($data as $key => $value) {
            $rows[] = [$key, is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_SLASHES)];
        }
        $io->table(['Field', 'Value'], $rows);

        return Command::SUCCESS;
    }
}
