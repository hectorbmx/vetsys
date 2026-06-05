<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\AnimalShare;
use App\Models\AnimalVideo;
use App\Models\RadiologyImage;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\VaccinationLetter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TelemedicineController extends Controller
{
    public function searchTenants(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $search = trim((string) $request->get('q', ''));

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $tenants = Tenant::query()
            ->where('id', '!=', $tenantId)
            ->where('status', 'active')
            ->where('is_active', true)
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $query->orWhere('id', (int) $search);
                }
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'slug', 'business_name']);

        return response()->json($tenants->map(fn (Tenant $tenant) => [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'business_name' => $tenant->business_name,
            'label' => trim("#{$tenant->id} {$tenant->name}"),
        ]));
    }

    public function store(Request $request, Animal $animal)
    {
        $tenantId = auth()->user()->tenant_id;

        abort_unless($animal->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'shared_with_tenant_id' => [
                'required',
                'integer',
                Rule::exists('tenants', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);

        if ((int) $data['shared_with_tenant_id'] === (int) $tenantId) {
            return back()->with('error', 'Selecciona un tenant distinto al propietario del expediente.');
        }

        $share = AnimalShare::query()
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('animal_id', $animal->id)
            ->where('shared_with_tenant_id', $data['shared_with_tenant_id'])
            ->first();

        if ($share) {
            $share->restore();
            $share->update([
                'shared_by_user_id' => auth()->id(),
                'token' => $share->token ?: Str::random(64),
                'is_active' => true,
                'expires_at' => null,
            ]);
        } else {
            $share = AnimalShare::create([
                'tenant_id' => $tenantId,
                'animal_id' => $animal->id,
                'shared_with_tenant_id' => $data['shared_with_tenant_id'],
                'shared_by_user_id' => auth()->id(),
                'token' => Str::random(64),
                'is_active' => true,
            ]);
        }

        $shareUrl = route('client.telemedicine.animals.show', $share->token);

        TenantNotification::create([
            'tenant_id' => $share->shared_with_tenant_id,
            'actor_tenant_id' => $tenantId,
            'actor_user_id' => auth()->id(),
            'type' => 'telemedicine_share_created',
            'title' => 'Expediente compartido',
            'body' => (auth()->user()->tenant->name ?? 'Otro tenant') . ' compartio contigo el expediente de ' . $animal->name . '.',
            'url' => $shareUrl,
            'data' => [
                'animal_share_id' => $share->id,
                'animal_id' => $animal->id,
                'animal_name' => $animal->name,
            ],
        ]);

        return back()
            ->with('success', 'Expediente compartido correctamente.')
            ->with('animalTab', 'telemedicina')
            ->with('telemedicine_link', $shareUrl);
    }

    public function destroy(AnimalShare $animalShare)
    {
        abort_unless($animalShare->tenant_id === auth()->user()->tenant_id, 404);

        $animalShare->update(['is_active' => false]);

        return back()
            ->with('animalTab', 'telemedicina')
            ->with('success', 'Acceso de telemedicina revocado correctamente.');
    }

    public function show(string $token)
    {
        $share = $this->authorizedShare($token);

        $share->forceFill(['last_accessed_at' => now()])->save();

        $animal = $share->animal()
            ->with([
                'tenant',
                'customer',
                'animalType',
                'club',
                'vaccinationLetters' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
                'videos' => fn ($query) => $query->latest('video_date')->latest('id'),
                'radiologyStudies' => fn ($query) => $query
                    ->with(['images' => fn ($imageQuery) => $imageQuery->latest('id')])
                    ->latest('study_date')
                    ->latest('id'),
            ])
            ->firstOrFail();

        $serviceHistory = $animal->noteDetails()
            ->where('tenant_id', $share->tenant_id)
            ->with(['note', 'catalogItem'])
            ->latest()
            ->get();

        return view('client.telemedicine.animals.show', compact('share', 'animal', 'serviceHistory'));
    }

    public function letter(string $token, VaccinationLetter $vaccinationLetter)
    {
        $share = $this->authorizedShare($token);

        abort_unless($vaccinationLetter->tenant_id === $share->tenant_id, 404);
        abort_unless($vaccinationLetter->animal_id === $share->animal_id, 404);
        abort_unless(Storage::disk('public')->exists($vaccinationLetter->image_path), 404);

        return response()->file(Storage::disk('public')->path($vaccinationLetter->image_path));
    }

    public function video(string $token, AnimalVideo $animalVideo)
    {
        $share = $this->authorizedShare($token);

        abort_unless($animalVideo->tenant_id === $share->tenant_id, 404);
        abort_unless($animalVideo->animal_id === $share->animal_id, 404);
        abort_unless(Storage::disk($animalVideo->disk)->exists($animalVideo->path), 404);

        return redirect()->away(
            Storage::disk($animalVideo->disk)->temporaryUrl($animalVideo->path, now()->addMinutes(30))
        );
    }

    public function radiologyImage(string $token, RadiologyImage $radiologyImage)
    {
        $share = $this->authorizedShare($token);

        abort_unless($radiologyImage->tenant_id === $share->tenant_id, 404);
        abort_unless($radiologyImage->animal_id === $share->animal_id, 404);
        abort_unless(Storage::disk($radiologyImage->disk)->exists($radiologyImage->path), 404);

        return redirect()->away(
            Storage::disk($radiologyImage->disk)->temporaryUrl($radiologyImage->path, now()->addMinutes(30))
        );
    }

    private function authorizedShare(string $token): AnimalShare
    {
        $share = AnimalShare::query()
            ->where('token', $token)
            ->where('is_active', true)
            ->where('shared_with_tenant_id', auth()->user()->tenant_id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with(['animal'])
            ->firstOrFail();

        abort_unless($share->animal && $share->animal->tenant_id === $share->tenant_id, 404);

        return $share;
    }
}
