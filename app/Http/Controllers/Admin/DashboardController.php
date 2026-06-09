<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantPayment;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Métricas de Clientes (Tenants)
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('is_active', true)->count();
        $inactiveTenants = Tenant::where('is_active', false)->count();

        // Clientes por vencer (suscripción termina en los próximos 7 días)
        $expiringTenantsCount = Tenant::where('is_active', true)
            ->whereNotNull('subscription_ends_at')
            ->whereBetween('subscription_ends_at', [now(), now()->addDays(7)])
            ->count();

        // 2. Métricas Financieras (Pagos de suscripciones)
        $totalIncome = (float) TenantPayment::where('status', 'succeeded')->sum('amount');
        $monthlyIncome = (float) TenantPayment::where('status', 'succeeded')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        // 3. Últimos Clientes Registrados
        $recentTenants = Tenant::with('plan')
            ->latest()
            ->take(5)
            ->get();

        // 4. Últimos Pagos Recibidos
        $recentPayments = TenantPayment::with('tenant')
            ->where('status', 'succeeded')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalTenants',
            'activeTenants',
            'inactiveTenants',
            'expiringTenantsCount',
            'totalIncome',
            'monthlyIncome',
            'recentTenants',
            'recentPayments'
        ));
    }
}