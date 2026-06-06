<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        $methods = PaymentMethod::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $methods->map(fn (PaymentMethod $method) => [
                'id' => $method->id,
                'name' => $method->name,
                'slug' => $method->slug,
                'description' => $method->description,
                'is_active' => $method->is_active,
            ]),
        ]);
    }
}
