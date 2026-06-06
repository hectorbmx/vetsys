<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use Illuminate\Http\Request;

class ClubController extends Controller
{
    public function index(Request $request)
    {
        $clubs = Club::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $clubs->map(fn (Club $club) => [
                'id' => $club->id,
                'name' => $club->name,
                'is_active' => $club->is_active,
            ]),
        ]);
    }
}
