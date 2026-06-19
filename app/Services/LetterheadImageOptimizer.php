<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class LetterheadImageOptimizer
{
    private const MAX_WIDTH = 2600;

    private const MAX_HEIGHT = 1200;

    public function optimize(UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new RuntimeException('No fue posible procesar la imagen del membrete.');
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min(1, self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);

            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 255, 255, 255, 127);
            imagefill($target, 0, 0, $transparent);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            ob_start();
            $encoded = imagewebp($target, null, 92);
            $contents = ob_get_clean();
            imagedestroy($target);

            if (! $encoded || $contents === false) {
                throw new RuntimeException('No fue posible optimizar la imagen del membrete.');
            }

            return $contents;
        } finally {
            imagedestroy($source);
        }
    }
}
