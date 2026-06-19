<?php

namespace App\Services;

use App\Models\Animal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MicrochipLetterPdfService
{
    public function __construct(private readonly DocumentPresentationService $presentation) {}

    public function ensureFinalized(Animal $animal): void
    {
        if ($animal->microchip_pdf_path && Storage::disk($animal->microchip_pdf_disk ?: 'r2')->exists($animal->microchip_pdf_path)) {
            return;
        }

        $this->finalize($animal);
    }

    public function finalize(Animal $animal): void
    {
        $animal->load(['tenant.documentSetting', 'customer', 'animalType', 'microchipIssuer.veterinarianProfile']);
        abort_unless($animal->microchip_image_path, 404);

        $finalizedAt = now();
        $documentPresentation = $this->presentation->build(
            $animal->tenant,
            $animal,
            $animal->microchipIssuer,
            TenantDocumentTemplateService::MICROCHIP,
            $finalizedAt,
            ['microchip_number' => $animal->microchip ?: 'sin numero registrado']
        );
        $imageDataUri = $this->presentation->dataUri('r2', $animal->microchip_image_path, 'image/webp');
        $pdf = Pdf::loadView('client.animals.microchip-letter', compact(
            'animal',
            'documentPresentation',
            'imageDataUri',
            'finalizedAt'
        ))
            ->setPaper('letter', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true);

        $path = "tenants/{$animal->tenant_id}/animals/{$animal->id}/microchip/letters/".Str::uuid().'.pdf';
        Storage::disk('r2')->put($path, $pdf->output(), ['mimetype' => 'application/pdf']);

        $oldPath = $animal->microchip_pdf_path;
        $oldDisk = $animal->microchip_pdf_disk;

        try {
            $animal->update([
                'microchip_pdf_disk' => 'r2',
                'microchip_pdf_path' => $path,
                'microchip_finalized_at' => $finalizedAt,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('r2')->delete($path);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $path) {
            try {
                Storage::disk($oldDisk ?: 'r2')->delete($oldPath);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    public function temporaryUrl(Animal $animal): string
    {
        $this->ensureFinalized($animal);
        $animal->refresh();

        return Storage::disk($animal->microchip_pdf_disk ?: 'r2')->temporaryUrl(
            $animal->microchip_pdf_path,
            now()->addMinutes(30),
            ['ResponseContentDisposition' => 'inline; filename="carta-microchip-'.$animal->id.'.pdf"']
        );
    }
}
