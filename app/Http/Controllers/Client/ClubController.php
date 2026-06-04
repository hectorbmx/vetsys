<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\Club;
use Illuminate\Http\Request;
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
            ->with(['customer', 'animalType', 'club'])
            ->orderBy('name')
            ->get();

        return view('client.clubes.index', compact('clubs', 'animals'));
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
            ->route('client.clubes.index')
            ->with('success', 'Club actualizado correctamente.');
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
            ->route('client.clubes.index')
            ->with('success', 'Miembros del club actualizados correctamente.');
    }
}
