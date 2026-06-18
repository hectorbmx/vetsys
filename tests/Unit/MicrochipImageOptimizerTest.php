<?php

namespace Tests\Unit;

use App\Services\MicrochipImageOptimizer;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class MicrochipImageOptimizerTest extends TestCase
{
    public function test_it_converts_and_resizes_an_uploaded_image(): void
    {
        $source = imagecreatetruecolor(3000, 1500);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'microchip-');
        imagejpeg($source, $temporaryPath, 90);
        imagedestroy($source);

        try {
            $file = new UploadedFile($temporaryPath, 'microchip.jpg', 'image/jpeg', null, true);
            $contents = (new MicrochipImageOptimizer())->optimize($file);
            $imageInfo = getimagesizefromstring($contents);

            $this->assertNotFalse($imageInfo);
            $this->assertSame(2000, $imageInfo[0]);
            $this->assertSame(1000, $imageInfo[1]);
            $this->assertSame('image/webp', $imageInfo['mime']);
        } finally {
            @unlink($temporaryPath);
        }
    }
}
