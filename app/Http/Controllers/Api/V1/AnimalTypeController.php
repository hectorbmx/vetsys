<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnimalType;
use Illuminate\Http\Request;

class AnimalTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = AnimalType::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $types->map(fn (AnimalType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'is_active' => $type->is_active,
            ]),
        ]);
    }
}
