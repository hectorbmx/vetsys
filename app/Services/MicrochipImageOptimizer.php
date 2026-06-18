<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class MicrochipImageOptimizer
{
    private const MAX_DIMENSION = 2000;

    public function optimize(UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('No fue posible procesar la foto del microchip.');
        }

        try {
            $source = $this->applyExifOrientation($source, $file);
            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min(1, self::MAX_DIMENSION / max($width, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);

            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 255, 255, 255, 127);
            imagefill($target, 0, 0, $transparent);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            ob_start();
            $encoded = imagewebp($target, null, 86);
            $contents = ob_get_clean();
            imagedestroy($target);

            if (! $encoded || $contents === false) {
                throw new RuntimeException('No fue posible optimizar la foto del microchip.');
            }

            return $contents;
        } finally {
            imagedestroy($source);
        }
    }

    private function applyExifOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        if ($file->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }
}
