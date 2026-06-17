<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\AnimalVideo;
use App\Services\PortalNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class AnimalVideoController extends Controller
{
    public function store(Request $request, Animal $animal)
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $tenantId = auth()->user()->tenant_id;

        abort_unless($animal->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'video_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string'],
            'video' => ['required', 'file', 'mimes:mp4,mov,avi,webm,mkv', 'max:102400'],
        ]);

        $file = $request->file('video');
        $processedVideo = $this->optimizedVideoPath($file->getRealPath(), $file->getClientOriginalExtension());
        $extension = $processedVideo['extension'];
        $filename = Str::uuid() . '.' . $extension;
        $path = "tenants/{$tenantId}/animals/{$animal->id}/videos/{$filename}";

        $stream = fopen($processedVideo['path'], 'rb');

        try {
            Storage::disk('r2')->put($path, $stream, [
                'mimetype' => $processedVideo['mime_type'],
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            $this->deleteTempFile($processedVideo['delete_path']);
        }

        $video = AnimalVideo::create([
            'tenant_id' => $tenantId,
            'animal_id' => $animal->id,
            'disk' => 'r2',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $processedVideo['mime_type'],
            'size' => $processedVideo['size'],
            'video_date' => $data['video_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        app(PortalNotificationService::class)->videoPublished($video);

        return redirect()
            ->route('client.animals.edit', $animal)
            ->with('animalTab', 'videos')
            ->with('success', 'Video guardado correctamente.');
    }

    public function show(AnimalVideo $animalVideo)
    {
        abort_unless($animalVideo->tenant_id === auth()->user()->tenant_id, 404);
        abort_unless(Storage::disk($animalVideo->disk)->exists($animalVideo->path), 404);

        return redirect()->away(
            Storage::disk($animalVideo->disk)->temporaryUrl($animalVideo->path, now()->addMinutes(30))
        );
    }

    public function destroy(AnimalVideo $animalVideo)
    {
        abort_unless($animalVideo->tenant_id === auth()->user()->tenant_id, 404);

        Storage::disk($animalVideo->disk)->delete($animalVideo->path);
        $animal = $animalVideo->animal;
        $animalVideo->delete();

        return redirect()
            ->route('client.animals.edit', $animal)
            ->with('animalTab', 'videos')
            ->with('success', 'Video eliminado correctamente.');
    }

    private function optimizedVideoPath(string $sourcePath, ?string $originalExtension): array
    {
        $originalSize = filesize($sourcePath) ?: 0;
        $originalExtension = strtolower($originalExtension ?: 'mp4');
        $fallback = [
            'path' => $sourcePath,
            'delete_path' => null,
            'extension' => $originalExtension,
            'mime_type' => mime_content_type($sourcePath) ?: 'video/mp4',
            'size' => $originalSize,
        ];

        if (!$this->ffmpegIsAvailable()) {
            return $fallback;
        }

        $tempDir = storage_path('app/video-processing');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $outputPath = $tempDir . DIRECTORY_SEPARATOR . Str::uuid() . '.mp4';

        $process = new Process([
            $this->ffmpegBinary(),
            '-y',
            '-i',
            $sourcePath,
            '-map',
            '0:v:0',
            '-map',
            '0:a?',
            '-vf',
            'scale=w=min(1280\\,iw):h=min(720\\,ih):force_original_aspect_ratio=decrease',
            '-c:v',
            'libx264',
            '-preset',
            'veryfast',
            '-crf',
            '28',
            '-c:a',
            'aac',
            '-b:a',
            '128k',
            '-movflags',
            '+faststart',
            $outputPath,
        ]);
        $process->setTimeout(1800);
        try {
            $process->run();
        } catch (\Throwable) {
            $this->deleteTempFile($outputPath);
            return $fallback;
        }

        if (!$process->isSuccessful() || !is_file($outputPath)) {
            $this->deleteTempFile($outputPath);
            return $fallback;
        }

        $optimizedSize = filesize($outputPath) ?: 0;

        if ($optimizedSize <= 0 || ($originalSize > 0 && $optimizedSize >= $originalSize)) {
            $this->deleteTempFile($outputPath);
            return $fallback;
        }

        return [
            'path' => $outputPath,
            'delete_path' => $outputPath,
            'extension' => 'mp4',
            'mime_type' => 'video/mp4',
            'size' => $optimizedSize,
        ];
    }

    private function ffmpegIsAvailable(): bool
    {
        $process = new Process([$this->ffmpegBinary(), '-version']);
        $process->setTimeout(10);
        try {
            $process->run();
        } catch (\Throwable) {
            return false;
        }

        return $process->isSuccessful();
    }

    private function ffmpegBinary(): string
    {
        return config('services.ffmpeg.path', 'ffmpeg');
    }

    private function deleteTempFile(?string $path): void
    {
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }
}
