<?php

namespace App\Services\Branding;

use Illuminate\Support\Facades\Storage;

final class FaviconGenerator
{
    public const OUTPUT_DIR = 'company-branding';

    public const OUTPUT_32 = 'company-branding/favicon-32.png';

    public const OUTPUT_64 = 'company-branding/favicon-64.png';

    /**
     * Build square favicon PNGs from any uploaded logo (wide logos are center-cropped).
     *
     * @return string Storage path to 32×32 favicon (for app_settings)
     */
    public function generateFromPublicDisk(string $sourceRelativePath): string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($sourceRelativePath)) {
            throw new \InvalidArgumentException('Logo file not found: '.$sourceRelativePath);
        }

        $absolute = $disk->path($sourceRelativePath);
        $image = $this->loadImage($absolute);
        if ($image === null) {
            throw new \RuntimeException('Could not read image for favicon generation.');
        }

        $disk->makeDirectory(self::OUTPUT_DIR);

        foreach ([32 => self::OUTPUT_32, 64 => self::OUTPUT_64] as $size => $target) {
            $square = $this->resizeSquare($image, $size);
            ob_start();
            imagepng($square, null, 9);
            $png = ob_get_clean();
            imagedestroy($square);
            if ($png === false || $png === '') {
                throw new \RuntimeException('Favicon PNG export failed.');
            }
            $disk->put($target, $png);
        }

        imagedestroy($image);

        $this->publishToPublicRoot($disk->path(self::OUTPUT_32));

        return self::OUTPUT_32;
    }

    public function publishToPublicRoot(string $source32Absolute): void
    {
        if (! is_file($source32Absolute)) {
            return;
        }

        @copy($source32Absolute, public_path('favicon.png'));
    }

    /**
     * @return \GdImage|null
     */
    private function loadImage(string $path): ?\GdImage
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }

        return match ($info[2]) {
            IMAGETYPE_PNG => @imagecreatefrompng($path) ?: null,
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path) ?: null,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            IMAGETYPE_GIF => @imagecreatefromgif($path) ?: null,
            default => null,
        };
    }

    /**
     * @param  \GdImage  $source
     */
    private function resizeSquare($source, int $size): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $min = min($width, $height);
        // Wide banner logos (e.g. company wordmark): take the left square, not center "DIANT".
        $srcX = $width > (int) ($height * 1.35)
            ? 0
            : (int) max(0, ($width - $min) / 2);
        $srcY = (int) max(0, ($height - $min) / 2);

        $target = imagecreatetruecolor($size, $size);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $size, $size, $transparent);

        imagecopyresampled($target, $source, 0, 0, $srcX, $srcY, $size, $size, $min, $min);

        return $target;
    }
}
