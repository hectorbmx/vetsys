<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;

use Illuminate\Support\Str;
class PaymentMethodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
   public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        // Recuperamos el tenant desde la sesión o del usuario autenticado (ajusta según tu lógica del SaaS)
        $tenant = auth()->user()->tenant; 
        $slug = Str::slug($request->name);

        // Validación para evitar colisiones del mismo método en la misma veterinaria
        $exists = $tenant->paymentMethods()->where('slug', $slug)->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Este método de pago ya está registrado en tu clínica.'])->with('currentTab', 'pagos');
        }

        $tenant->paymentMethods()->create([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'is_active' => true
        ]);

        return back()->with('success', 'Método de pago registrado correctamente.')->with('currentTab', 'pagos');
    }

    public function toggleStatus(PaymentMethod $paymentMethod)
    {
        // Seguridad preventiva básica del Tenant
        if ($paymentMethod->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $paymentMethod->update([
            'is_active' => !$paymentMethod->is_active
        ]);

        return back()->with('success', 'Estado del método de pago actualizado.')->with('currentTab', 'pagos');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
