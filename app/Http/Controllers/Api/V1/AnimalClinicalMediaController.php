<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\AnimalShare;
use App\Models\AnimalVideo;
use App\Models\RadiologyImage;
use App\Models\RadiologyStudy;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\VaccinationLetter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AnimalClinicalMediaController extends Controller
{
    public function index(Request $request, Animal $animal)
    {
        $this->authorizeAnimal($request, $animal);

        return response()->json(['data' => $this->serialize($animal)]);
    }

    public function storeVaccination(Request $request, Animal $animal)
    {
        $this->authorizeAnimal($request, $animal);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $existingLetters = $animal->vaccinationLetters()
            ->where('tenant_id', $animal->tenant_id)
            ->oldest('id')
            ->get();

        foreach ($existingLetters->slice(1) as $letter) {
            Storage::disk('public')->delete($letter->image_path);
            $letter->delete();
        }

        $path = $request->file('image')->store(
            "tenants/{$animal->tenant_id}/animals/{$animal->id}/vaccination-letters",
            'public'
        );

        VaccinationLetter::create([
            'tenant_id' => $animal->tenant_id,
            'animal_id' => $animal->id,
            'image_path' => $path,
            'date' => $data['date'],
        ]);

        return response()->json(['data' => $this->serialize($animal)], 201);
    }

    public function storeVideo(Request $request, Animal $animal)
    {
        $this->authorizeAnimal($request, $animal);
        $data = $request->validate([
            'video_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string'],
            'video' => ['required', 'file', 'mimes:mp4,mov,avi,webm,mkv', 'max:102400'],
        ]);

        $file = $request->file('video');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'mp4');
        $path = "tenants/{$animal->tenant_id}/animals/{$animal->id}/videos/" . Str::uuid() . ".{$extension}";
        Storage::disk('r2')->put($path, fopen($file->getRealPath(), 'rb'), ['mimetype' => $file->getMimeType()]);

        AnimalVideo::create([
            'tenant_id' => $animal->tenant_id,
            'animal_id' => $animal->id,
            'disk' => 'r2',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'video_date' => $data['video_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $this->serialize($animal)], 201);
    }

    public function storeRadiologyStudy(Request $request, Animal $animal)
    {
        $this->authorizeAnimal($request, $animal);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'study_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string'],
        ]);

        RadiologyStudy::create([
            'tenant_id' => $animal->tenant_id,
            'animal_id' => $animal->id,
            ...$data,
        ]);

        return response()->json(['data' => $this->serialize($animal)], 201);
    }

    public function storeRadiologyImages(Request $request, RadiologyStudy $radiologyStudy)
    {
        abort_unless($radiologyStudy->tenant_id === $request->user()->tenant_id, 404);
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'images' => ['required', 'array', 'min:1', 'max:20'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ]);

        foreach ($request->file('images', []) as $image) {
            $extension = strtolower($image->getClientOriginalExtension() ?: 'jpg');
            $path = "tenants/{$radiologyStudy->tenant_id}/animals/{$radiologyStudy->animal_id}/radiology/{$radiologyStudy->id}/" . Str::uuid() . ".{$extension}";
            Storage::disk('r2')->put($path, fopen($image->getRealPath(), 'rb'), ['mimetype' => $image->getMimeType()]);

            RadiologyImage::create([
                'tenant_id' => $radiologyStudy->tenant_id,
                'animal_id' => $radiologyStudy->animal_id,
                'radiology_study_id' => $radiologyStudy->id,
                'disk' => 'r2',
                'path' => $path,
                'original_name' => $image->getClientOriginalName(),
                'mime_type' => $image->getMimeType(),
                'size' => $image->getSize(),
                'label' => $data['label'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        }

        return response()->json(['data' => $this->serialize($radiologyStudy->animal)], 201);
    }

    public function searchTenants(Request $request)
    {
        $search = trim((string) $request->get('q', ''));
        if (strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $tenants = Tenant::query()
            ->where('id', '!=', $request->user()->tenant_id)
            ->where('status', 'active')
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('business_name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'business_name']);

        return response()->json(['data' => $tenants]);
    }

    public function share(Request $request, Animal $animal)
    {
        $this->authorizeAnimal($request, $animal);
        $data = $request->validate([
            'shared_with_tenant_id' => [
                'required',
                'integer',
                Rule::exists('tenants', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);

        $share = AnimalShare::withTrashed()->firstOrNew([
            'tenant_id' => $animal->tenant_id,
            'animal_id' => $animal->id,
            'shared_with_tenant_id' => $data['shared_with_tenant_id'],
        ]);
        $share->fill([
            'shared_by_user_id' => $request->user()->id,
            'token' => $share->token ?: Str::random(64),
            'is_active' => true,
            'expires_at' => null,
        ]);
        $share->restore();
        $share->save();

        $shareUrl = route('client.telemedicine.animals.show', $share->token);
        TenantNotification::create([
            'tenant_id' => $share->shared_with_tenant_id,
            'actor_tenant_id' => $animal->tenant_id,
            'actor_user_id' => $request->user()->id,
            'type' => 'telemedicine_share_created',
            'title' => 'Expediente compartido',
            'body' => ($request->user()->tenant->name ?? 'Otra clinica') . " compartio el expediente de {$animal->name}.",
            'url' => $shareUrl,
            'data' => ['animal_share_id' => $share->id, 'animal_id' => $animal->id],
        ]);

        return response()->json(['data' => $this->serialize($animal), 'share_url' => $shareUrl], 201);
    }

    private function authorizeAnimal(Request $request, Animal $animal): void
    {
        abort_unless($animal->tenant_id === $request->user()->tenant_id, 404);
    }

    private function serialize(Animal $animal): array
    {
        $animal->load([
            'vaccinationLetters' => fn ($query) => $query->latest('date')->latest('id'),
            'videos' => fn ($query) => $query->latest('video_date')->latest('id'),
            'radiologyStudies' => fn ($query) => $query->with('images')->latest('study_date')->latest('id'),
            'shares' => fn ($query) => $query->with('sharedWithTenant')->where('is_active', true)->latest('id'),
        ]);

        return [
            'vaccination_letters' => $animal->vaccinationLetters->map(fn ($letter) => [
                'id' => $letter->id,
                'date' => $letter->date?->toDateString(),
                'image_url' => request()->getSchemeAndHttpHost() . '/storage/' . ltrim($letter->image_path, '/'),
            ])->values(),
            'videos' => $animal->videos->map(fn ($video) => [
                'id' => $video->id,
                'video_date' => $video->video_date?->toDateString(),
                'notes' => $video->notes,
                'original_name' => $video->original_name,
                'url' => Storage::disk($video->disk)->temporaryUrl($video->path, now()->addMinutes(30)),
            ])->values(),
            'radiology_studies' => $animal->radiologyStudies->map(fn ($study) => [
                'id' => $study->id,
                'name' => $study->name,
                'study_date' => $study->study_date?->toDateString(),
                'notes' => $study->notes,
                'images' => $study->images->map(fn ($image) => [
                    'id' => $image->id,
                    'label' => $image->label,
                    'notes' => $image->notes,
                    'url' => Storage::disk($image->disk)->temporaryUrl($image->path, now()->addMinutes(30)),
                ])->values(),
            ])->values(),
            'shares' => $animal->shares->map(fn ($share) => [
                'id' => $share->id,
                'tenant_name' => $share->sharedWithTenant?->name,
                'url' => route('client.telemedicine.animals.show', $share->token),
            ])->values(),
        ];
    }
}
