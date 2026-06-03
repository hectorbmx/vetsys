<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Exception;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $customers = Customer::query()
            ->where('tenant_id', $tenantId)
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->q;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('client.customers.index', compact('customers'));
    }

    // public function create()
    // {
    //     return view('client.customers.create');
    // }

  public function store(Request $request)
{
    $tenantId = auth()->user()->tenant_id;

    // 1. Validamos solo lo que viene estrictamente del formulario modal
    $data = $request->validate([
        'name'            => ['required', 'string', 'max:255'],
        'last_name'       => ['required', 'string', 'max:255'], // Lo cambié a required si lo pides con (*) en el modal
        'email'           => ['required', 'email', 'max:255'],    // Lo cambié a required por el (*) del modal
        'phone'           => ['required', 'string', 'max:50'],    // Lo cambié a required por el (*) del modal
        'secondary_phone' => ['nullable', 'string', 'max:50'],
        'address'         => ['nullable', 'string'],
        'notes'           => ['nullable', 'string'],
    ]);

    // 2. Inyectamos los parámetros obligatorios del backend
    $data['tenant_id'] = $tenantId;
    $data['status']    = 'active'; // Se registra activo por defecto automáticamente

    try {
        Customer::create($data);

        return redirect()
            ->route('client.customers.index')
            ->with('success', '¡Excelente! El cliente ha sido registrado con éxito.');

    } catch (Exception $e) {
        // En caso de un fallo físico de inserción, devolvemos el mensaje de alerta
        return redirect()
            ->back()
            ->withInput()
            ->with('error', 'Hubo un problema al guardar en la base de datos. Inténtalo de nuevo.');
    }
}

// En CustomerController.php
// CustomerController.php
// CustomerController.php
public function show($id)
{
    $customer = Customer::with([
        'saleNotes',
        'animals',
        'payments',
        'accountSetting',
        'statements' => fn ($query) => $query->latest(),
    ])->findOrFail($id);
    $this->authorizeTenant($customer);

    $paymentMethods = \App\Models\PaymentMethod::where('tenant_id', auth()->user()->tenant_id)
    ->where('is_active', true)
    ->get();
       // NUEVO: tipos de animal activos del tenant para el modal
    $animalTypes = \App\Models\AnimalType::where('tenant_id', auth()->user()->tenant_id)
        ->where('is_active', true)
        ->orderBy('name')
        ->get();

    // return view('client.customers.show', compact('customer','paymentMethods'));
        return view('client.customers.show', compact('customer', 'paymentMethods', 'animalTypes'));

}

    public function updateAccountSettings(Request $request, Customer $customer)
    {
        $this->authorizeTenant($customer);

        $tenantId = auth()->user()->tenant_id;

        $data = $request->validate([
            'cutoff_day' => ['required', 'integer', 'between:1,31'],
            'preferred_payment_method_id' => [
                'nullable',
                Rule::exists('payment_methods', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'credit_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_statement_enabled' => ['nullable', 'boolean'],
        ]);

        $data['tenant_id'] = $tenantId;
        $data['is_statement_enabled'] = $request->boolean('is_statement_enabled');

        $customer->accountSetting()->updateOrCreate(
            ['tenant_id' => $tenantId, 'customer_id' => $customer->id],
            $data
        );

        return redirect()
            ->route('client.customers.show', $customer)
            ->with('success', 'Configuracion contable actualizada correctamente.');
    }

    public function edit(Customer $customer)
    {
        $this->authorizeTenant($customer);

        return view('client.customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeTenant($customer);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'secondary_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $customer->update($data);

        return redirect()
            ->route('client.customers.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorizeTenant($customer);

        $customer->delete();

        return redirect()
            ->route('client.customers.index')
            ->with('success', 'Cliente eliminado correctamente.');
    }

    private function authorizeTenant(Customer $customer): void
    {
        abort_if(
            $customer->tenant_id !== auth()->user()->tenant_id,
            403,
            'No tienes permiso para acceder a este cliente.'
        );
    }
}
