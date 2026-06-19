<?php

namespace App\Services;

use App\Models\VaccinationLetter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class VaccinationLetterPdfService
{
    public function __construct(private readonly DocumentPresentationService $presentation) {}

    public function ensureFinalized(VaccinationLetter $letter): void
    {
        if ($letter->pdf_path && Storage::disk($letter->pdf_disk ?: 'r2')->exists($letter->pdf_path)) {
            return;
        }

        $this->finalize($letter);
    }

    public function finalize(VaccinationLetter $letter): void
    {
        $letter->load(['tenant.documentSetting', 'animal.customer', 'animal.animalType', 'publisher.veterinarianProfile']);
        $documentPresentation = $this->presentation->build(
            $letter->tenant,
            $letter->animal,
            $letter->publisher,
            TenantDocumentTemplateService::VACCINATION,
            $letter->date,
            [
                'vaccination_date' => $letter->date->format('d/m/Y'),
                'vaccine_name' => $letter->vaccine_name ?: 'vacuna registrada',
            ]
        );
        $imageDataUri = $this->presentation->dataUri('public', $letter->image_path);
        $finalizedAt = now();
        $pdf = Pdf::loadView('client.animals.vaccination-letter-pdf', compact(
            'letter',
            'documentPresentation',
            'imageDataUri',
            'finalizedAt'
        ))
            ->setPaper('letter', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true);

        $path = "tenants/{$letter->tenant_id}/animals/{$letter->animal_id}/vaccination-letters/{$letter->id}/".
            Str::uuid().'.pdf';
        Storage::disk('r2')->put($path, $pdf->output(), ['mimetype' => 'application/pdf']);

        $oldPath = $letter->pdf_path;
        $oldDisk = $letter->pdf_disk;

        try {
            $letter->update([
                'pdf_disk' => 'r2',
                'pdf_path' => $path,
                'finalized_at' => $finalizedAt,
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

    public function temporaryUrl(VaccinationLetter $letter): string
    {
        $this->finalize($letter);
        $letter->refresh();

        return Storage::disk($letter->pdf_disk ?: 'r2')->temporaryUrl(
            $letter->pdf_path,
            now()->addMinutes(30),
            ['ResponseContentDisposition' => 'inline; filename="carta-vacunacion-'.$letter->id.'.pdf"']
        );
    }
}
