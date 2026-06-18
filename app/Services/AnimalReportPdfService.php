<?php

namespace App\Services;

use App\Models\AnimalReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AnimalReportPdfService
{
    public function finalize(AnimalReport $report): void
    {
        $report->load(['tenant', 'animal.customer', 'animal.animalType', 'author', 'images']);
        $finalizedAt = now();

        $imageData = $report->images->map(fn ($image) => [
            'name' => $image->original_name,
            'data_uri' => $this->dataUri($image->disk, $image->path, $image->mime_type),
        ])->filter(fn ($image) => $image['data_uri'])->values();

        $logoDataUri = $this->tenantLogoDataUri($report);
        $pdf = Pdf::loadView('client.animals.reports.pdf', compact('report', 'imageData', 'logoDataUri', 'finalizedAt'))
            ->setPaper('letter', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true);

        $path = "tenants/{$report->tenant_id}/animals/{$report->animal_id}/reports/{$report->id}/".
            Str::uuid().'.pdf';

        Storage::disk('r2')->put($path, $pdf->output(), ['mimetype' => 'application/pdf']);

        try {
            $report->update([
                'status' => 'finalized',
                'pdf_disk' => 'r2',
                'pdf_path' => $path,
                'finalized_at' => $finalizedAt,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('r2')->delete($path);
            throw $exception;
        }
    }

    private function dataUri(string $disk, string $path, ?string $mimeType = null): ?string
    {
        try {
            if (! Storage::disk($disk)->exists($path)) {
                return null;
            }

            return 'data:'.($mimeType ?: 'image/webp').';base64,'.base64_encode(Storage::disk($disk)->get($path));
        } catch (Throwable) {
            return null;
        }
    }

    private function tenantLogoDataUri(AnimalReport $report): ?string
    {
        $path = $report->tenant?->logo;
        if (! $path || filter_var($path, FILTER_VALIDATE_URL)) {
            return null;
        }

        foreach (['public', 'r2'] as $disk) {
            $data = $this->dataUri($disk, $path, $this->mimeTypeFromPath($path));
            if ($data) {
                return $data;
            }
        }

        return null;
    }

    private function mimeTypeFromPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
