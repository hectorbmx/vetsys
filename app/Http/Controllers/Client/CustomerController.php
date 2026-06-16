<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Note;
use App\Models\CustomerPaymentLink;
use App\Services\CustomerStripePaymentProcessor;
use App\Services\CustomerPortalAccessService;
use App\Services\StripeCustomerPaymentService;
use App\Services\TenantOnboardingService;
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
        ->with('portalAccesses')
        ->withCount('animals')
        ->withSum([
            'saleNotes as general_debt' => fn ($query) => $query->where('status', 'PENDIENTE'),
        ], 'total')
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

    public function toggleStatus(Customer $customer)
    {
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $customer->update([
            'status' => $customer->status === 'active' ? 'inactive' : 'active'
        ]);

        if ($customer->status === 'active') {
            app(TenantOnboardingService::class)->reconcileSafely(auth()->user()->tenant);
        }

        return back()->with('success', 'El estatus del cliente ha sido actualizado.');
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

        app(TenantOnboardingService::class)->reconcileSafely(auth()->user()->tenant);

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
    $this->reconcilePendingStripePayments((int) $id);

    $customer = Customer::with([
        'saleNotes.details.catalogItem',
        'saleNotes.details.animal',
        'saleNotes.payments.paymentMethod',
        'animals',
        'payments.paymentMethod',
        'accountSetting',
        'portalUserLinks.user',
        'portalAccesses.user',
        'finalUserPatientAssignments.animal',
        'animalPortalVisibilitySettings',
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

    $clubs = \App\Models\Club::where('tenant_id', auth()->user()->tenant_id)
        ->where('is_active', true)
        ->orderBy('name')
        ->get();

    // return view('client.customers.show', compact('customer','paymentMethods'));
        return view('client.customers.show', compact('customer', 'paymentMethods', 'animalTypes', 'clubs'));

}

    private function reconcilePendingStripePayments(int $customerId): void
    {
        $processedPayment = false;
        $links = CustomerPaymentLink::where('customer_id', $customerId)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('status', 'pending')
            ->whereNotNull('stripe_checkout_session_id')
            ->get();

        foreach ($links as $paymentLink) {
            try {
                $session = app(StripeCustomerPaymentService::class)
                    ->retrieveCheckoutSession($paymentLink->stripe_checkout_session_id);

                if (($session->payment_status ?? null) === 'paid' && is_string($session->payment_intent ?? null)) {
                    $payment = app(CustomerStripePaymentProcessor::class)->process(
                        $paymentLink,
                        $session->payment_intent,
                        $session->id
                    );
                    $processedPayment = $processedPayment || (bool) $payment;
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($processedPayment) {
            app(TenantOnboardingService::class)->reconcileSafely(auth()->user()->tenant);
        }
    }

    public function togglePortalAccess(Customer $customer, CustomerPortalAccessService $portalAccessService)
    {
        $this->authorizeTenant($customer);

        $activeAccess = $customer->portalAccesses()
            ->where('status', 'active')
            ->first();

        try {
            if ($activeAccess) {
                $portalAccessService->suspend($customer, auth()->user());

                return back()->with('success', 'Acceso app/web suspendido para este cliente.');
            }

            $result = $portalAccessService->activate($customer, auth()->user());
            $message = 'Acceso app/web activado para este cliente.';

            if ($result['invitation_url']) {
                session()->flash('activation_link', $result['invitation_url']);
                session()->flash('activation_code', $result['activation_code']);
                session()->flash('activation_email', $result['user']->email);
            }

            if ($result['created_user'] && $result['mail_sent']) {
                $message .= ' Se envio la invitacion por correo y tambien se muestra en pantalla.';
            } elseif ($result['created_user'] && !$result['mail_sent']) {
                $message .= ' No se pudo enviar el correo; copia el enlace/codigo de invitacion mostrado.';
            } elseif ($result['invitation_url'] && !$result['mail_sent']) {
                $message .= ' Se genero un nuevo enlace de invitacion; copialo desde esta pantalla.';
            } elseif ($result['invitation_url']) {
                $message .= ' Se muestra el enlace de invitacion en pantalla.';
            }

            return back()->with('success', $message);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }
    }

    public function updateAnimalPortalVisibility(Request $request, Customer $customer, CustomerPortalAccessService $portalAccessService)
    {
        $this->authorizeTenant($customer);

        $data = $request->validate([
            'animal_id' => [
                'required',
                'integer',
                Rule::exists('animals', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->where('customer_id', $customer->id)),
            ],
            'is_shared' => ['nullable', 'boolean'],
            'show_profile' => ['nullable', 'boolean'],
            'show_history' => ['nullable', 'boolean'],
            'show_notes' => ['nullable', 'boolean'],
            'show_services' => ['nullable', 'boolean'],
            'show_products' => ['nullable', 'boolean'],
            'show_files' => ['nullable', 'boolean'],
            'show_videos' => ['nullable', 'boolean'],
            'show_radiology' => ['nullable', 'boolean'],
            'show_statement' => ['nullable', 'boolean'],
            'show_vaccines' => ['nullable', 'boolean'],
            'show_appointments' => ['nullable', 'boolean'],
        ]);

        $data['is_shared'] = $request->boolean('is_shared');

        foreach (CustomerPortalAccessService::VISIBILITY_FIELDS as $field) {
            $data[$field] = $request->boolean($field);
        }

        try {
            $portalAccessService->updateAnimalVisibility($customer, (int) $data['animal_id'], auth()->user(), $data);

            return back()
                ->with('success', 'Visibilidad app/web actualizada para la mascota.')
                ->with('activeCustomerTab', 'mascotas');
        } catch (\Throwable $exception) {
            report($exception);

            return back()
                ->with('error', $exception->getMessage())
                ->with('activeCustomerTab', 'mascotas');
        }
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

        if ($customer->status === 'active') {
            app(TenantOnboardingService::class)->reconcileSafely(auth()->user()->tenant);
        }

        return redirect()
            ->route('client.customers.show', [$customer, 'tab' => 'datos'])
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
