<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\Club;
use App\Models\Coggin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClubController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->tenant_id;

        $clubs = Club::query()
            ->where('tenant_id', $tenantId)
            ->withCount('animals')
            ->with(['animals' => fn ($query) => $query
                ->with(['customer', 'animalType'])
                ->orderBy('name')])
            ->latest()
            ->get();

        $animals = Animal::query()
            ->where('tenant_id', $tenantId)
            ->get();

        return view('client.clubes.index', compact('clubs', 'animals'));
    }

    public function toggleStatus(Club $club)
    {
        if ($club->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $club->update([
            'is_active' => !$club->is_active
        ]);

        return back()->with('success', 'El estatus del club ha sido actualizado.');
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('clubs', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        Club::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('client.clubes.index')
            ->with('success', 'Club creado correctamente.');
    }

    public function update(Request $request, Club $clube)
    {
        $tenantId = auth()->user()->tenant_id;
        abort_unless($clube->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('clubs', 'name')
                    ->ignore($clube->id)
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $clube->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('client.clubes.edit', $clube)
            ->with('success', 'Club actualizado correctamente.');
    }

    public function edit(Club $clube)
    {
        $tenantId = auth()->user()->tenant_id;
        abort_unless($clube->tenant_id === $tenantId, 404);

        $clube->load(['animals.customer', 'animals.animalType', 'coggins']);

        return view('client.clubes.edit', [
            'club' => $clube,
        ]);
    }

    public function storeCoggin(Request $request, Club $club)
    {
        $tenantId = auth()->user()->tenant_id;
        abort_unless($club->tenant_id === $tenantId, 404);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'], // 10MB max
        ]);

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $path = $file->store("tenants/{$tenantId}/clubs/{$club->id}/coggins", 'public');

        $club->coggins()->create([
            'tenant_id' => $tenantId,
            'file_path' => $path,
            'file_name' => $fileName,
        ]);

        return redirect()
            ->route('client.clubes.edit', $club)
            ->with('success', 'Archivo Coggin subido correctamente.');
    }

    public function destroyCoggin(Club $club, Coggin $coggin)
    {
        $tenantId = auth()->user()->tenant_id;
        abort_unless($club->tenant_id === $tenantId && $coggin->club_id === $club->id, 404);

        \Storage::disk('public')->delete($coggin->file_path);
        $coggin->delete();

        return redirect()
            ->route('client.clubes.edit', $club)
            ->with('success', 'Archivo Coggin eliminado.');
    }

    public function searchAnimals(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $search = $request->get('q');

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $animals = Animal::query()
            ->where('tenant_id', $tenantId)
            ->where('name', 'LIKE', "%{$search}%")
            ->with(['customer', 'animalType'])
            ->take(10)
            ->get()
            ->map(function ($animal) {
                return [
                    'id' => $animal->id,
                    'name' => $animal->name,
                    'customer' => $animal->customer->full_name ?? 'N/A',
                    'type' => $animal->animalType->name ?? 'N/A',
                ];
            });

        return response()->json($animals);
    }

    public function updateMembers(Request $request, Club $club)
    {
        $tenantId = auth()->user()->tenant_id;
        abort_unless($club->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'animal_ids' => ['nullable', 'array'],
            'animal_ids.*' => [
                'integer',
                Rule::exists('animals', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ]);

        $animalIds = collect($data['animal_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        Animal::query()
            ->where('tenant_id', $tenantId)
            ->where('club_id', $club->id)
            ->whereNotIn('id', $animalIds)
            ->update(['club_id' => null]);

        Animal::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $animalIds)
            ->update(['club_id' => $club->id]);

        return redirect()
            ->route('client.clubes.edit', [$club, 'tab' => 'miembros'])
            ->with('success', 'Miembros del club actualizados correctamente.');
    }
}
