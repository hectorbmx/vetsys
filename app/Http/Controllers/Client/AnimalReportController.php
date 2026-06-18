<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\AnimalReport;
use App\Models\AnimalReportImage;
use App\Services\AnimalReportImageOptimizer;
use App\Services\AnimalReportPdfService;
use App\Services\RichTextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AnimalReportController extends Controller
{
    public function store(
        Request $request,
        Animal $animal,
        RichTextSanitizer $sanitizer,
        AnimalReportImageOptimizer $imageOptimizer,
        AnimalReportPdfService $pdfService
    ) {
        $tenantId = auth()->user()->tenant_id;
        abort_unless($animal->tenant_id === $tenantId, 404);

        $data = $this->validatedData($request, $sanitizer);
        $report = AnimalReport::create([
            'tenant_id' => $tenantId,
            'animal_id' => $animal->id,
            'author_id' => auth()->id(),
            'title' => $data['title'],
            'report_date' => $data['report_date'],
            'content_html' => $data['content_html'],
            'status' => 'draft',
        ]);

        try {
            $this->storeImages($request, $report, $imageOptimizer);

            if ($data['intent'] === 'finalize') {
                $pdfService->finalize($report);
            }
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('client.animal-reports.edit', $report)
                ->with('error', 'El reporte quedo guardado como borrador, pero no se pudo completar el procesamiento: '.$exception->getMessage());
        }

        return redirect()
            ->route('client.animals.edit', $animal)
            ->with('animalTab', 'reportes')
            ->with('success', $report->fresh()->isDraft()
                ? 'Reporte guardado como borrador.'
                : 'Reporte finalizado y PDF generado correctamente.');
    }

    public function edit(AnimalReport $animalReport)
    {
        $this->authorizeTenant($animalReport);
        abort_unless($animalReport->isDraft(), 409, 'Los reportes finalizados no se pueden editar.');

        $animalReport->load(['animal.customer', 'animal.animalType', 'images']);

        return view('client.animals.reports.edit', [
            'report' => $animalReport,
            'animal' => $animalReport->animal,
        ]);
    }

    public function update(
        Request $request,
        AnimalReport $animalReport,
        RichTextSanitizer $sanitizer,
        AnimalReportImageOptimizer $imageOptimizer,
        AnimalReportPdfService $pdfService
    ) {
        $this->authorizeTenant($animalReport);
        abort_unless($animalReport->isDraft(), 409, 'Los reportes finalizados no se pueden editar.');

        $data = $this->validatedData($request, $sanitizer);
        $animalReport->update([
            'title' => $data['title'],
            'report_date' => $data['report_date'],
            'content_html' => $data['content_html'],
        ]);

        try {
            $this->storeImages($request, $animalReport, $imageOptimizer);

            if ($data['intent'] === 'finalize') {
                $pdfService->finalize($animalReport);
            }
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('client.animal-reports.edit', $animalReport)
                ->with('error', 'Los cambios quedaron como borrador, pero no se pudo completar el procesamiento: '.$exception->getMessage());
        }

        return redirect()
            ->route('client.animals.edit', $animalReport->animal_id)
            ->with('animalTab', 'reportes')
            ->with('success', $animalReport->fresh()->isDraft()
                ? 'Borrador actualizado correctamente.'
                : 'Reporte finalizado y PDF generado correctamente.');
    }

    public function pdf(AnimalReport $animalReport)
    {
        $this->authorizeTenant($animalReport);
        abort_unless($animalReport->status === 'finalized' && $animalReport->pdf_path, 404);
        abort_unless(Storage::disk($animalReport->pdf_disk)->exists($animalReport->pdf_path), 404);

        return redirect()->away(
            Storage::disk($animalReport->pdf_disk)->temporaryUrl(
                $animalReport->pdf_path,
                now()->addMinutes(30),
                ['ResponseContentDisposition' => 'inline; filename="reporte-'.$animalReport->id.'.pdf"']
            )
        );
    }

    public function publicPdf(string $token)
    {
        $report = AnimalReport::query()
            ->where('public_token', $token)
            ->where('status', 'finalized')
            ->whereNotNull('pdf_path')
            ->firstOrFail();

        abort_unless(Storage::disk($report->pdf_disk)->exists($report->pdf_path), 404);

        return redirect()->away(
            Storage::disk($report->pdf_disk)->temporaryUrl(
                $report->pdf_path,
                now()->addMinutes(30),
                ['ResponseContentDisposition' => 'inline; filename="reporte-'.$report->id.'.pdf"']
            )
        );
    }

    public function image(AnimalReportImage $animalReportImage)
    {
        $report = $animalReportImage->report;
        $this->authorizeTenant($report);
        abort_unless(Storage::disk($animalReportImage->disk)->exists($animalReportImage->path), 404);

        return redirect()->away(
            Storage::disk($animalReportImage->disk)->temporaryUrl($animalReportImage->path, now()->addMinutes(30))
        );
    }

    public function destroyImage(AnimalReportImage $animalReportImage)
    {
        $report = $animalReportImage->report;
        $this->authorizeTenant($report);
        abort_unless($report->isDraft(), 409, 'No se pueden modificar imagenes de un reporte finalizado.');

        Storage::disk($animalReportImage->disk)->delete($animalReportImage->path);
        $animalReportImage->delete();

        return redirect()
            ->route('client.animal-reports.edit', $report)
            ->with('success', 'Imagen eliminada del borrador.');
    }

    public function destroy(AnimalReport $animalReport)
    {
        $this->authorizeTenant($animalReport);
        abort_unless($animalReport->isDraft(), 409, 'Un reporte finalizado forma parte del historial y no se puede eliminar.');

        $animal = $animalReport->animal;
        $animalReport->load('images');
        foreach ($animalReport->images as $image) {
            Storage::disk($image->disk)->delete($image->path);
        }
        $animalReport->delete();

        return redirect()
            ->route('client.animals.edit', $animal)
            ->with('animalTab', 'reportes')
            ->with('success', 'Borrador eliminado correctamente.');
    }

    private function validatedData(Request $request, RichTextSanitizer $sanitizer): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'content_html' => ['required', 'string', 'max:200000'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'intent' => ['required', 'in:draft,finalize'],
        ]);

        $data['content_html'] = $sanitizer->sanitize($data['content_html']);
        $plainText = trim(str_replace("\xc2\xa0", ' ', html_entity_decode(strip_tags($data['content_html']))));
        if ($plainText === '') {
            throw ValidationException::withMessages([
                'content_html' => 'Escribe el contenido clinico del reporte.',
            ]);
        }

        return $data;
    }

    private function storeImages(
        Request $request,
        AnimalReport $report,
        AnimalReportImageOptimizer $imageOptimizer
    ): void {
        $files = $request->file('images', []);
        if ($report->images()->count() + count($files) > 10) {
            throw ValidationException::withMessages([
                'images' => 'El reporte puede contener un maximo de 10 imagenes.',
            ]);
        }

        $position = (int) $report->images()->max('position');

        foreach ($files as $file) {
            $position++;
            $path = "tenants/{$report->tenant_id}/animals/{$report->animal_id}/reports/{$report->id}/images/".
                Str::uuid().'.webp';
            $contents = $imageOptimizer->optimize($file);
            Storage::disk('r2')->put($path, $contents, ['mimetype' => 'image/webp']);

            try {
                AnimalReportImage::create([
                    'tenant_id' => $report->tenant_id,
                    'animal_report_id' => $report->id,
                    'disk' => 'r2',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => 'image/webp',
                    'size' => strlen($contents),
                    'position' => $position,
                ]);
            } catch (\Throwable $exception) {
                Storage::disk('r2')->delete($path);
                throw $exception;
            }
        }
    }

    private function authorizeTenant(AnimalReport $report): void
    {
        abort_unless($report->tenant_id === auth()->user()->tenant_id, 404);
        abort_unless($report->animal && $report->animal->tenant_id === auth()->user()->tenant_id, 404);
    }
}
