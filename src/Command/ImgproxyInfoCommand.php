<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle\Command;

use Survos\ImgproxyBundle\Service\ImgproxyUrlBuilder;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    'imgproxy:info',
    'Fetch image metadata from imgproxy\'s PRO /info endpoint',
    help: <<<'HELP'
        Query imgproxy's PRO <info>/info</info> endpoint for a source image. The source may be
        an <info>https://</info> URL or, when imgproxy has IMGPROXY_USE_S3=true, an <info>s3://bucket/key</info> URL.

        Pass options with repeatable <info>--opt</info>. Tokens use imgproxy's name:value form;
        a bare name implies <info>:1</info>. Friendly aliases (blurhash, thumb_hash, …) map to the
        short names below.

        <comment>Always returned (the defaults):</comment>
          size              file size in bytes
          format            image format + mime_type
          dimensions        width, height, orientation
          exif, iptc, xmp   embedded metadata blocks (kept only if not stripped)
          video_meta        codec/duration/fps for video

        <comment>Perceptual hashes:</comment>
          thumb_hash        --opt=thumb_hash         (placeholder hash, hex)
          perceptual_hash   --opt=perceptual_hash    (phash; Hamming-distance compare)
          blurhash:X:Y      --opt=blurhash:4:3       ⚠ needs TWO components (1–9); a bare
                                                     "blurhash" sends bh:1 and 404s

        <comment>Color:</comment>
          average           --opt=average            (mean R,G,B,A)
          dominant_colors   --opt=dominant_colors    (vibrant/muted variants)
          palette:N         --opt=palette:8          (N = 2–256 colors)

        <comment>ML (Pro; needs models loaded server-side):</comment>
          detect_objects    --opt=detect_objects:1   (boxes + class_name + confidence)
          classify:N        --opt=classify:5         (top-N labelled categories)

        <comment>Examples:</comment>
          <info>bin/console imgproxy:info https://example.com/a.jpg --opt=exif --json</info>
          <info>bin/console imgproxy:info s3://museado/orig/ab/cd/<hash>.jpg \
              --opt=dimensions --opt=blurhash:4:3 --opt=classify:5 --opt=detect_objects:1 --json</info>
        HELP,
)]
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
