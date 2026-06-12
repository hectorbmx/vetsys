<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\TenantOnboardingStep;
use App\Services\TenantOnboardingService;

class DashboardController extends Controller
{
    public function index()
    {
        $tenant = auth()->user()->tenant;

        $totalCustomers = $tenant->customers()->count();
        $activeCustomers = $tenant->customers()->where('status', 'active')->count();

        $totalAnimals = $tenant->animals()->count();
        $activeAnimals = $tenant->animals()->where('status', 'active')->count();

        $notesQuery = $tenant->notes()->where('status', '!=', 'CANCELADA');
        $totalNotes = (clone $notesQuery)->count();
        $pendingNotes = (clone $notesQuery)->where('status', 'PENDIENTE')->count();
        $paidNotes = (clone $notesQuery)->where('status', 'PAGADA')->count();

        $totalSold = (float) (clone $notesQuery)->sum('total');
        $totalCollected = (float) $tenant->clientPayments()->sum('amount');
        $totalReceivable = max($totalSold - $totalCollected, 0);

        $recentNotes = $tenant->notes()
            ->with('customer')
            ->latest()
            ->take(5)
            ->get();

        $onboarding = app(TenantOnboardingService::class)->reconcileSafely($tenant);

        if ($onboarding) {
            $stepPresentation = $this->onboardingStepPresentation();
            $onboarding['steps'] = collect($onboarding['steps'])
                ->map(function (array $step) use ($stepPresentation, $onboarding) {
                    return array_merge($step, $stepPresentation[$step['key']], [
                        'is_next' => $step['key'] === $onboarding['next_step'],
                    ]);
                })
                ->all();
        }

        return view('client.dashboard', compact(
            'totalCustomers',
            'activeCustomers',
            'totalAnimals',
            'activeAnimals',
            'totalNotes',
            'pendingNotes',
            'paidNotes',
            'totalSold',
            'totalCollected',
            'totalReceivable',
            'recentNotes',
            'onboarding'
        ));
    }

    private function onboardingStepPresentation(): array
    {
        return [
            TenantOnboardingStep::CLINIC_CONFIGURED => [
                'label' => 'Configura tu clinica',
                'description' => 'Agrega una especie y un metodo de pago activos.',
                'action_label' => 'Ir a configuracion',
                'action_url' => route('client.mi-configuracion.index'),
            ],
            TenantOnboardingStep::FIRST_SERVICE_CREATED => [
                'label' => 'Agrega tu primer servicio',
                'description' => 'Crea al menos un servicio activo para vender.',
                'action_label' => 'Ir a servicios',
                'action_url' => route('client.servicios.index'),
            ],
            TenantOnboardingStep::FIRST_CUSTOMER_CREATED => [
                'label' => 'Registra tu primer cliente',
                'description' => 'Crea el expediente del primer propietario.',
                'action_label' => 'Ir a clientes',
                'action_url' => route('client.customers.index'),
            ],
            TenantOnboardingStep::FIRST_PET_CREATED => [
                'label' => 'Registra tu primera mascota',
                'description' => 'Asigna una mascota activa a uno de tus clientes.',
                'action_label' => 'Ir a mascotas',
                'action_url' => route('client.animals.index'),
            ],
            TenantOnboardingStep::FIRST_NOTE_CREATED => [
                'label' => 'Crea tu primera nota',
                'description' => 'Registra una venta con mascota y servicios.',
                'action_label' => 'Crear nota',
                'action_url' => route('client.ventas.create'),
            ],
            TenantOnboardingStep::FIRST_NOTE_PAID => [
                'label' => 'Cobra tu primera nota',
                'description' => 'Liquida una nota mediante un pago real aplicado.',
                'action_label' => 'Ir a ventas',
                'action_url' => route('client.ventas.index'),
            ],
        ];
    }
}
