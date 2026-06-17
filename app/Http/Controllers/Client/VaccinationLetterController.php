<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\VaccinationLetter;
use App\Services\PortalNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VaccinationLetterController extends Controller
{
    public function show(VaccinationLetter $vaccinationLetter)
    {
        abort_unless($vaccinationLetter->tenant_id === auth()->user()->tenant_id, 404);
        abort_unless(Storage::disk('public')->exists($vaccinationLetter->image_path), 404);

        return response()->file(Storage::disk('public')->path($vaccinationLetter->image_path));
    }

    public function print(VaccinationLetter $vaccinationLetter)
    {
        $tenantId = auth()->user()->tenant_id;

        abort_unless($vaccinationLetter->tenant_id === $tenantId, 404);

        return $this->renderPdf($vaccinationLetter);
    }

    public function signedPrint(Request $request, VaccinationLetter $vaccinationLetter)
    {
        abort_unless($request->hasValidRelativeSignature(), 403);

        return $this->renderPdf($vaccinationLetter);
    }

    private function renderPdf(VaccinationLetter $vaccinationLetter)
    {
        $vaccinationLetter->load(['tenant', 'animal.customer', 'animal.animalType']);
        abort_unless(Storage::disk('public')->exists($vaccinationLetter->image_path), 404);

        $imageDataUri = $this->storageImageAsDataUri($vaccinationLetter->image_path);
        $tenantLogoDataUri = $vaccinationLetter->tenant->logo
            ? $this->storageImageAsDataUri($vaccinationLetter->tenant->logo)
            : null;

        $pdf = Pdf::loadView('client.animals.vaccination-letter-pdf', [
            'letter' => $vaccinationLetter,
            'animal' => $vaccinationLetter->animal,
            'customer' => $vaccinationLetter->animal->customer,
            'tenant' => $vaccinationLetter->tenant,
            'imageDataUri' => $imageDataUri,
            'tenantLogoDataUri' => $tenantLogoDataUri,
            'generatedDate' => Carbon::now()->format('Y-m-d'),
        ])
            ->setPaper('letter', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        $filename = 'carta-vacunacion-' . str($vaccinationLetter->animal->name)->slug() . '-' . $vaccinationLetter->date->format('Ymd') . '.pdf';

        return $pdf->stream($filename);
    }

    public function store(Request $request, Animal $animal)
    {
        $tenantId = auth()->user()->tenant_id;

        abort_unless($animal->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'date' => ['required', 'date'],
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
        ]);

        app(PortalNotificationService::class)->vaccinationLetterPublished($letter);

        return redirect()
            ->route('client.animals.edit', $animal)
            ->with('success', 'Carta de vacunacion guardada correctamente.');
    }

    private function storageImageAsDataUri(?string $path): ?string
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($path);
        $mime = mime_content_type($fullPath) ?: 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
    }
}
