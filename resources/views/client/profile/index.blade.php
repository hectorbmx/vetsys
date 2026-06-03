@extends('layouts.client')

@section('title', 'Perfil')

@section('content')
<div class="space-y-8">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-[#38B2AC]">Perfil de Veterinaria</p>
            <h1 class="text-3xl md:text-4xl font-black text-[#0F172A] tracking-tight mt-1">
                {{ $tenant->business_name ?? $tenant->name }}
            </h1>
            <p class="text-sm font-semibold text-slate-400 mt-2">
                Datos generales, plan contratado y estado de suscripcion.
            </p>
        </div>

        <div class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl {{ $tenant->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
            <span class="w-2 h-2 rounded-full {{ $tenant->status === 'active' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
            <span class="text-[10px] font-black uppercase tracking-widest">{{ $tenant->status }}</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
            <div class="h-28 bg-gradient-to-br from-[#38B2AC] via-emerald-400 to-cyan-400"></div>
            <div class="px-6 pb-6 -mt-10">
                <div class="w-24 h-24 rounded-3xl bg-white border-4 border-white shadow-lg overflow-hidden flex items-center justify-center">
                    @if($tenant->logo)
                        <img src="{{ asset('storage/' . $tenant->logo) }}" alt="{{ $tenant->name }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full bg-[#0F172A] text-white flex items-center justify-center text-3xl font-black">
                            {{ substr($tenant->name, 0, 1) }}
                        </div>
                    @endif
                </div>

                <h2 class="text-xl font-black text-[#0F172A] mt-4">{{ $tenant->business_name ?? $tenant->name }}</h2>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">{{ $tenant->name }}</p>

                <div class="mt-6 space-y-3">
                    <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Email</p>
                        <p class="text-sm font-bold text-[#0F172A] mt-1">{{ $tenant->email ?? 'No configurado' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Telefono</p>
                        <p class="text-sm font-bold text-[#0F172A] mt-1">{{ $tenant->phone ?? 'No configurado' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Slug</p>
                        <p class="text-sm font-bold text-[#0F172A] mt-1">{{ $tenant->slug }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="relative overflow-hidden bg-[#0F172A] rounded-[24px] p-6 shadow-xl shadow-slate-200 min-h-[210px]">
                    <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-[#38B2AC]/30"></div>
                    <div class="relative z-10">
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-[#38B2AC]">Plan Contratado</p>
                        <h3 class="text-3xl font-black text-white mt-4">{{ $tenant->plan->name ?? 'Sin plan' }}</h3>
                        <p class="text-sm font-semibold text-slate-300 mt-2">{{ $tenant->plan->description ?? 'Sin descripcion disponible.' }}</p>

                        <div class="mt-6 flex items-end justify-between gap-4">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Precio</p>
                                <p class="text-2xl font-black text-white">
                                    @if($tenant->plan)
                                        ${{ number_format($tenant->plan->price, 2) }} {{ $tenant->plan->currency }}
                                    @else
                                        --
                                    @endif
                                </p>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-widest bg-white/10 text-white px-3 py-2 rounded-xl">
                                {{ $tenant->plan->billing_period ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-[24px] p-6 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Suscripcion</p>
                    <h3 class="text-2xl font-black text-[#0F172A] mt-3">
                        {{ $currentSubscription->status ?? $tenant->status }}
                    </h3>

                    <div class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="font-bold text-slate-400">Inicio</span>
                            <span class="font-black text-[#0F172A]">{{ optional($currentSubscription?->starts_at)->format('d/m/Y') ?? '--' }}</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span class="font-bold text-slate-400">Vence</span>
                            <span class="font-black {{ $tenant->subscription_ends_at && $tenant->subscription_ends_at->isPast() ? 'text-rose-600' : 'text-[#0F172A]' }}">
                                {{ optional($tenant->subscription_ends_at ?? $currentSubscription?->ends_at)->format('d/m/Y') ?? '--' }}
                            </span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span class="font-bold text-slate-400">Trial</span>
                            <span class="font-black text-[#0F172A]">{{ optional($tenant->trial_ends_at ?? $currentSubscription?->trial_ends_at)->format('d/m/Y') ?? '--' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white border border-slate-200 rounded-[20px] p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Usuarios max.</p>
                    <p class="text-2xl font-black text-[#0F172A] mt-2">{{ $tenant->plan->max_users ?? 'Sin limite' }}</p>
                </div>
                <div class="bg-white border border-slate-200 rounded-[20px] p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Clientes max.</p>
                    <p class="text-2xl font-black text-[#0F172A] mt-2">{{ $tenant->plan->max_clients ?? 'Sin limite' }}</p>
                </div>
                <div class="bg-white border border-slate-200 rounded-[20px] p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Ultimo pago</p>
                    <p class="text-2xl font-black text-[#0F172A] mt-2">
                        {{ $lastPayment ? '$' . number_format($lastPayment->amount, 2) : '--' }}
                    </p>
                    @if($lastPayment)
                        <p class="text-[10px] font-bold text-slate-400 mt-1">{{ optional($lastPayment->paid_at)->format('d/m/Y') ?? $lastPayment->created_at->format('d/m/Y') }}</p>
                    @endif
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Renovacion</h3>
                        <p class="text-[11px] font-semibold text-slate-400 mt-1">Fase 1 informativa. La renovacion en linea se activara mas adelante.</p>
                    </div>
                    <button type="button" disabled class="bg-slate-100 text-slate-400 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest cursor-not-allowed">
                        Solicitar renovacion
                    </button>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rounded-2xl bg-amber-50 border border-amber-100 p-4">
                        <p class="text-xs font-black text-amber-900">Renovacion asistida</p>
                        <p class="text-[11px] font-semibold text-amber-700 mt-1">Por ahora el administrador registra pagos y cambios de plan manualmente.</p>
                    </div>
                    <div class="rounded-2xl bg-teal-50 border border-teal-100 p-4">
                        <p class="text-xs font-black text-teal-900">Pago en linea futuro</p>
                        <p class="text-[11px] font-semibold text-teal-700 mt-1">Esta seccion queda preparada para conectar checkout y webhooks despues.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100">
                    <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Pagos de suscripcion</h3>
                </div>
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] text-slate-400 uppercase tracking-widest border-b border-slate-100">
                            <th class="px-6 py-3">Fecha</th>
                            <th class="px-6 py-3">Plan</th>
                            <th class="px-6 py-3">Metodo</th>
                            <th class="px-6 py-3">Estado</th>
                            <th class="px-6 py-3 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse($tenant->payments->take(5) as $payment)
                            <tr>
                                <td class="px-6 py-4 text-xs font-bold text-slate-600">{{ optional($payment->paid_at)->format('d/m/Y') ?? $payment->created_at->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 text-xs font-bold text-[#0F172A]">{{ $payment->plan->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 text-xs font-semibold text-slate-500">{{ $payment->payment_method ?? 'Manual' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest {{ $payment->status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $payment->status }}</span>
                                </td>
                                <td class="px-6 py-4 text-xs font-black text-right text-[#0F172A]">${{ number_format($payment->amount, 2) }} {{ $payment->currency }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-xs font-bold text-slate-400">Sin pagos de suscripcion registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
