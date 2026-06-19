<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\VaccinationLetter;
use App\Services\PortalNotificationService;
use App\Services\VaccinationLetterPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VaccinationLetterController extends Controller
{
    public function show(VaccinationLetter $vaccinationLetter)
    {
        abort_unless($vaccinationLetter->tenant_id === auth()->user()->tenant_id, 404);
        abort_unless($this->publicStorageFileExists($vaccinationLetter->image_path), 404);

        return response()->file($this->publicStorageFilePath($vaccinationLetter->image_path));
    }

    public function print(VaccinationLetter $vaccinationLetter, VaccinationLetterPdfService $pdfService)
    {
        $tenantId = auth()->user()->tenant_id;

        abort_unless($vaccinationLetter->tenant_id === $tenantId, 404);

        return redirect()->away($pdfService->temporaryUrl($vaccinationLetter));
    }

    public function signedPrint(Request $request, VaccinationLetter $vaccinationLetter, VaccinationLetterPdfService $pdfService)
    {
        abort_unless($request->hasValidSignature() || $request->hasValidRelativeSignature(), 403);

        return redirect()->away($pdfService->temporaryUrl($vaccinationLetter));
    }

    public function publicPrint(string $token, VaccinationLetterPdfService $pdfService)
    {
        $vaccinationLetter = VaccinationLetter::query()
            ->where('public_token', $token)
            ->firstOrFail();

        return redirect()->away($pdfService->temporaryUrl($vaccinationLetter));
    }

    public function store(Request $request, Animal $animal, VaccinationLetterPdfService $pdfService)
    {
        $tenantId = auth()->user()->tenant_id;

        abort_unless($animal->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'vaccine_name' => ['required', 'string', 'max:255'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $letters = $animal->vaccinationLetters()
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($letters->count() >= 2) {
            $lettersToDelete = $letters->slice(1);

            foreach ($lettersToDelete as $letter) {
                Storage::disk('public')->delete($letter->image_path);
                if ($letter->pdf_path) {
                    Storage::disk($letter->pdf_disk ?: 'r2')->delete($letter->pdf_path);
                }
                $letter->delete();
            }
        }

        $path = $request->file('image')->store(
            "tenants/{$tenantId}/animals/{$animal->id}/vaccination-letters",
            'public'
        );

        $letter = VaccinationLetter::create([
            'tenant_id' => $tenantId,
            'animal_id' => $animal->id,
            'image_path' => $path,
            'date' => $data['date'],
            'vaccine_name' => $data['vaccine_name'],
            'published_by' => auth()->id(),
        ]);

        try {
            $pdfService->finalize($letter);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('client.animals.edit', $animal)
                ->with('animalTab', 'vacunacion')
                ->with('error', 'La carta quedo guardada, pero no se pudo generar el PDF. Podras intentarlo al abrirla.');
        }

        app(PortalNotificationService::class)->vaccinationLetterPublished($letter);

        return redirect()
            ->route('client.animals.edit', $animal)
            ->with('success', 'Carta de vacunacion guardada correctamente.');
    }

    private function publicStorageFileExists(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        return Storage::disk('public')->exists($path)
            || is_file(public_path('storage/' . ltrim($path, '/')));
    }

    private function publicStorageFilePath(string $path): string
    {
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->path($path);
        }

        return public_path('storage/' . ltrim($path, '/'));
    }
}
