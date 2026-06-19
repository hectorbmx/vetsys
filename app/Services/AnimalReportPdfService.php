<?php

namespace App\Services;

use App\Models\AnimalReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AnimalReportPdfService
{
    public function __construct(private readonly DocumentPresentationService $presentation) {}

    public function finalize(AnimalReport $report): void
    {
        $report->load(['tenant.documentSetting', 'animal.customer', 'animal.animalType', 'author.veterinarianProfile', 'images']);
        $finalizedAt = now();

        $imageData = $report->images->map(fn ($image) => [
            'name' => $image->original_name,
            'data_uri' => $this->presentation->dataUri($image->disk, $image->path, $image->mime_type),
        ])->filter(fn ($image) => $image['data_uri'])->values();

        $documentPresentation = $this->presentation->build(
            $report->tenant,
            $report->animal,
            $report->author,
            TenantDocumentTemplateService::CLINICAL_REPORT,
            $report->report_date
        );
        $pdf = Pdf::loadView('client.animals.reports.pdf', compact('report', 'imageData', 'documentPresentation', 'finalizedAt'))
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
}
