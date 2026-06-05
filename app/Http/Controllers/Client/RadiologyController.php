<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\RadiologyImage;
use App\Models\RadiologyStudy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RadiologyController extends Controller
{
    public function storeStudy(Request $request, Animal $animal)
    {
        $tenantId = auth()->user()->tenant_id;

        abort_unless($animal->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'study_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string'],
        ]);

        RadiologyStudy::create([
            'tenant_id' => $tenantId,
            'animal_id' => $animal->id,
            'name' => $data['name'],
            'study_date' => $data['study_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('client.animals.edit', $animal)
            ->with('animalTab', 'radiologia')
            ->with('success', 'Carpeta de radiologia creada correctamente.');
    }

    public function storeImages(Request $request, RadiologyStudy $radiologyStudy)
    {
        $tenantId = auth()->user()->tenant_id;

        abort_unless($radiologyStudy->tenant_id === $tenantId, 404);
        abort_unless($radiologyStudy->animal && $radiologyStudy->animal->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'images' => ['required', 'array', 'min:1', 'max:20'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ]);

        foreach ($request->file('images', []) as $image) {
            $extension = strtolower($image->getClientOriginalExtension() ?: $image->extension() ?: 'jpg');
            $filename = Str::uuid() . '.' . $extension;
            $path = "tenants/{$tenantId}/animals/{$radiologyStudy->animal_id}/radiology/{$radiologyStudy->id}/{$filename}";
            $stream = fopen($image->getRealPath(), 'rb');
            $mimeType = $image->getMimeType() ?: 'image/jpeg';

            try {
                Storage::disk('r2')->put($path, $stream, [
                    'mimetype' => $mimeType,
                ]);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            RadiologyImage::create([
                'tenant_id' => $tenantId,
                'animal_id' => $radiologyStudy->animal_id,
                'radiology_study_id' => $radiologyStudy->id,
                'disk' => 'r2',
                'path' => $path,
                'original_name' => $image->getClientOriginalName(),
                'mime_type' => $mimeType,
                'size' => $image->getSize(),
                'label' => $data['label'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        }

        return redirect()
            ->route('client.animals.edit', $radiologyStudy->animal)
            ->with('animalTab', 'radiologia')
            ->with('success', 'Imagenes RX guardadas correctamente.');
    }

    public function showImage(RadiologyImage $radiologyImage)
    {
        abort_unless($radiologyImage->tenant_id === auth()->user()->tenant_id, 404);
        abort_unless(Storage::disk($radiologyImage->disk)->exists($radiologyImage->path), 404);

        return redirect()->away(
            Storage::disk($radiologyImage->disk)->temporaryUrl($radiologyImage->path, now()->addMinutes(30))
        );
    }

    public function destroyImage(RadiologyImage $radiologyImage)
    {
        abort_unless($radiologyImage->tenant_id === auth()->user()->tenant_id, 404);

        $animal = $radiologyImage->animal;
        Storage::disk($radiologyImage->disk)->delete($radiologyImage->path);
        $radiologyImage->delete();

        return redirect()
            ->route('client.animals.edit', $animal)
            ->with('animalTab', 'radiologia')
            ->with('success', 'Imagen RX eliminada correctamente.');
    }

    public function destroyStudy(RadiologyStudy $radiologyStudy)
    {
        abort_unless($radiologyStudy->tenant_id === auth()->user()->tenant_id, 404);

        $animal = $radiologyStudy->animal;
        $radiologyStudy->load('images');

        foreach ($radiologyStudy->images as $image) {
            Storage::disk($image->disk)->delete($image->path);
        }

        $radiologyStudy->delete();

        return redirect()
            ->route('client.animals.edit', $animal)
            ->with('animalTab', 'radiologia')
            ->with('success', 'Carpeta de radiologia eliminada correctamente.');
    }
}
