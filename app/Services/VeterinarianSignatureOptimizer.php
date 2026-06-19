<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class VeterinarianSignatureOptimizer
{
    private const MAX_DIMENSION = 1200;

    public function optimize(UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new RuntimeException('No fue posible procesar la imagen de la firma.');
        }

        try {
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
            $encoded = imagewebp($target, null, 90);
            $contents = ob_get_clean();
            imagedestroy($target);

            if (! $encoded || $contents === false) {
                throw new RuntimeException('No fue posible optimizar la imagen de la firma.');
            }

            return $contents;
        } finally {
            imagedestroy($source);
        }
    }
}
