@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
<div class="space-y-8">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-[#3b82f6]">Global Admin Control</p>
            <h1 class="text-3xl md:text-4xl font-black text-[#0F172A] tracking-tight mt-1">
                Dashboard Administrativo
            </h1>
            <p class="text-sm font-semibold text-slate-400 mt-2">
                Resumen global de clínicas, suscripciones y métricas del sistema.
            </p>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl px-5 py-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Estado del Sistema</p>
            <div class="flex items-center gap-2 mt-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <p class="text-sm font-black text-[#0F172A] uppercase tracking-tighter">Operativo</p>
            </div>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        
        {{-- Total Clínicas --}}
        <div class="relative overflow-hidden rounded-[24px] bg-[#1d2b4f] p-6 min-h-[190px] shadow-xl shadow-slate-200">
            <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full bg-[#3b82f6]/20"></div>
            <div class="relative z-10 h-full flex flex-col justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-300">Total Clínicas</p>
                    <div class="text-5xl font-black text-white mt-4">{{ number_format($totalTenants) }}</div>
                </div>
                <div class="flex items-center justify-between pt-6">
                    <span class="text-xs font-bold text-slate-300">Activas</span>
                    <span class="text-sm font-black text-white bg-white/10 px-3 py-1.5 rounded-full">{{ number_format($activeTenants) }}</span>
                </div>
            </div>
        </div>

        {{-- Por Vencer --}}
        <div class="relative overflow-hidden rounded-[24px] bg-gradient-to-br from-amber-500 to-orange-600 p-6 min-h-[190px] shadow-xl shadow-orange-100">
            <div class="absolute -right-8 -bottom-8 w-32 h-32 rounded-full bg-white/20"></div>
            <div class="relative z-10 h-full flex flex-col justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-white/80">Por Vencer (7d)</p>
                    <div class="text-5xl font-black text-white mt-4">{{ number_format($expiringTenantsCount) }}</div>
                </div>
                <div class="flex items-center justify-between pt-6">
                    <span class="text-xs font-bold text-white/80">Inactivas</span>
                    <span class="text-sm font-black text-white bg-white/20 px-3 py-1.5 rounded-full">{{ number_format($inactiveTenants) }}</span>
                </div>
            </div>
        </div>

        {{-- Ingresos Mensuales --}}
        <div class="relative overflow-hidden rounded-[24px] bg-gradient-to-br from-emerald-600 to-teal-500 p-6 min-h-[190px] shadow-xl shadow-teal-100">
            <div class="absolute -left-10 -bottom-10 w-36 h-36 rounded-full bg-white/15"></div>
            <div class="relative z-10 h-full flex flex-col justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-white/80">Este Mes</p>
                    <div class="text-4xl font-black text-white mt-4">${{ number_format($monthlyIncome, 2) }}</div>
                </div>
                <div class="bg-white/15 rounded-xl px-3 py-2 mt-6">
                    <p class="text-[10px] font-bold text-white/75">Meta Mensual</p>
                    <div class="h-1.5 w-full bg-white/20 rounded-full mt-1 overflow-hidden">
                        <div class="h-full bg-white rounded-full" style="width: 65%"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ingresos Totales --}}
        <div class="relative overflow-hidden rounded-[24px] bg-gradient-to-br from-violet-600 to-fuchsia-600 p-6 min-h-[190px] shadow-xl shadow-fuchsia-100">
            <div class="absolute right-0 top-0 w-28 h-28 rounded-bl-full bg-white/20"></div>
            <div class="relative z-10 h-full flex flex-col justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-white/80">Ingresos Totales</p>
                    <div class="text-4xl font-black text-white mt-4">${{ number_format($totalIncome, 2) }}</div>
                </div>
                <div class="pt-5">
                    <div class="flex items-center gap-2 text-white/90">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/20 text-[10px]">📈</span>
                        <span class="text-xs font-bold tracking-tight">Crecimiento sostenido</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tables Section --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        
        {{-- Clientes Recientes --}}
        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/30">
                <div>
                    <h2 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Clínicas Recientes</h2>
                    <p class="text-[11px] font-semibold text-slate-400 mt-1">Ultimas veterinarias registradas en la plataforma.</p>
                </div>
                <a href="{{ route('admin.tenants.index') }}" class="text-[10px] font-black uppercase tracking-widest text-[#3b82f6] hover:text-[#1d2b4f]">Ver todas</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Clínica</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Plan</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Registro</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentTenants as $tenant)
                            <tr class="hover:bg-slate-50/60 transition-colors text-xs">
                                <td class="px-6 py-4">
                                    <span class="font-black text-[#0F172A] block">{{ $tenant->name }}</span>
                                    <span class="text-[10px] text-slate-400 font-medium">{{ $tenant->email }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-slate-500">{{ $tenant->plan->name ?? 'Prueba' }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest {{ $tenant->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                        {{ $tenant->is_active ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-bold text-slate-500 text-right">{{ $tenant->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-xs font-bold text-slate-400">Sin registros recientes.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagos Recientes --}}
        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/30">
                <div>
                    <h2 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Pagos Recientes</h2>
                    <p class="text-[11px] font-semibold text-slate-400 mt-1">Últimas transacciones de suscripción procesadas.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Clínica</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Monto</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">ID Transacción</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentPayments as $payment)
                            <tr class="hover:bg-slate-50/60 transition-colors text-xs">
                                <td class="px-6 py-4 font-black text-[#0F172A]">{{ $payment->tenant->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4">
                                    <span class="font-black text-emerald-600">${{ number_format($payment->amount, 2) }}</span>
                                </td>
                                <td class="px-6 py-4 font-mono text-slate-400 text-[10px]">
                                    {{ substr($payment->stripe_payment_intent_id, 0, 12) }}...
                                </td>
                                <td class="px-6 py-4 font-bold text-slate-500 text-right">{{ $payment->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-xs font-bold text-slate-400">No hay pagos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
