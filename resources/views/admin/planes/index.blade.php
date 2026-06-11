@extends('layouts.admin')

@section('title', 'Planes')

@section('content')

<div class="space-y-8">

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header Section --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-4xl font-black text-[#0F172A] tracking-tighter">Planes</h1>
            <p class="text-slate-500 font-medium mt-1">
                Estructura de precios y capacidades del ecosistema SaaS.
            </p>
        </div>

        <a href="{{ route('admin.planes.create') }}"
           class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#0F172A] px-6 py-3.5 text-xs font-black text-white hover:bg-slate-800 transition-all shadow-lg shadow-slate-200 uppercase tracking-widest">
            <span>+</span>
            NUEVO PLAN
        </a>
    </div>

    {{-- Table Section --}}
    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        
        <div class="px-8 py-5 border-b border-slate-100 bg-slate-50/50">
            <h2 class="font-black text-[11px] uppercase tracking-[0.2em] text-slate-400">
                Configuración de Suscripciones
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm border-separate border-spacing-0">
                <thead>
                    <tr class="text-[#0F172A]">
                        <th class="px-8 py-4 text-left font-black uppercase tracking-widest text-[10px] bg-white">Plan</th>
                        <th class="px-8 py-4 text-left font-black uppercase tracking-widest text-[10px] bg-white">Precio</th>
                        <th class="px-8 py-4 text-left font-black uppercase tracking-widest text-[10px] bg-white">Límites de Uso</th>
                        <th class="px-8 py-4 text-left font-black uppercase tracking-widest text-[10px] bg-white">Estado</th>
                        <th class="px-8 py-4 text-left font-black uppercase tracking-widest text-[10px] bg-white">Stripe</th>
                        <th class="px-8 py-4 text-right font-black uppercase tracking-widest text-[10px] bg-white">Gestión</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse($plans as $plan)
                        <tr class="hover:bg-slate-50/80 transition-colors group">
                            
                            {{-- Info del Plan --}}
                            <td class="px-8 py-6">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-xl group-hover:bg-[#38B2AC]/10 group-hover:text-[#38B2AC] transition-colors">
                                        💎
                                    </div>
                                    <div>
                                        <p class="font-black text-[#0F172A] text-lg leading-tight">{{ $plan->name }}</p>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">{{ $plan->slug }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Precio con Alto Contraste --}}
                            <td class="px-8 py-6">
                                <div class="flex items-baseline gap-1">
                                    <span class="text-2xl font-black text-[#0F172A]">${{ number_format($plan->price, 0) }}</span>
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-tighter">
                                        {{ $plan->currency }} / {{ $plan->billing_period }}
                                    </span>
                                </div>
                            </td>

                            {{-- Límites con énfasis visual --}}
                            <td class="px-8 py-6">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#38B2AC]"></span>
                                        <p class="text-xs font-bold text-slate-600">
                                            Usuarios: <span class="text-[#0F172A] font-black">{{ $plan->max_users ?? 'Ilimitados' }}</span>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#38B2AC]"></span>
                                        <p class="text-xs font-bold text-slate-600">
                                            Clientes: <span class="text-[#0F172A] font-black">{{ $plan->max_clients ?? 'Ilimitados' }}</span>
                                        </p>
                                    </div>
                                    <p class="text-[10px] font-bold text-slate-500">Web: {{ $plan->web_access ? $plan->max_web_sessions_per_user . ' por usuario' : 'No' }}</p>
                                    <p class="text-[10px] font-bold text-slate-500">Movil: {{ $plan->mobile_access ? $plan->max_mobile_sessions_per_user . ' por usuario' : 'No' }}</p>
                                    <p class="text-[10px] font-bold text-slate-500">Simultaneas: {{ $plan->allow_cross_platform_sessions ? 'Web + movil' : 'Una plataforma' }}</p>
                                </div>
                            </td>

                            {{-- Estado con Badge moderno --}}
                            <td class="px-8 py-6">
                                @if($plan->is_active)
                                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#38B2AC]/10 px-3 py-1.5 text-[10px] font-black text-[#38B2AC] uppercase tracking-widest">
                                        <span class="relative flex h-2 w-2">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#38B2AC] opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2 w-2 bg-[#38B2AC]"></span>
                                        </span>
                                        Activo
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                        Inactivo
                                    </span>
                                @endif
                            </td>

                            {{-- Acción --}}
                            <td class="px-8 py-6">
                                @if($plan->stripe_product_id && $plan->stripe_price_id)
                                    <span class="inline-flex items-center rounded-lg bg-violet-50 px-3 py-1.5 text-[10px] font-black text-violet-700 uppercase tracking-widest">
                                        Stripe OK
                                    </span>
                                    <div class="mt-2 space-y-1 text-[10px] font-semibold text-slate-400">
                                        <p>Product: {{ $plan->stripe_product_id }}</p>
                                        <p>Price: {{ $plan->stripe_price_id }}</p>
                                    </div>
                                @else
                                    <span class="inline-flex items-center rounded-lg bg-amber-50 px-3 py-1.5 text-[10px] font-black text-amber-700 uppercase tracking-widest">
                                        Pendiente
                                    </span>
                                    <p class="mt-2 text-[10px] font-semibold text-slate-400">
                                        Falta sincronizar Product y Price.
                                    </p>
                                @endif
                            </td>

                            <td class="px-8 py-6 text-right">
                                <div class="flex flex-col items-end gap-2">
                                    <form action="{{ route('admin.planes.sync-stripe', $plan) }}" method="POST">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-[#635BFF] text-[10px] font-black text-white uppercase tracking-widest hover:bg-[#5148d8] transition-all shadow-sm">
                                            Sincronizar Stripe
                                        </button>
                                    </form>

                                    <a href="{{ route('admin.planes.edit', $plan) }}"
                                       class="inline-flex items-center justify-center px-4 py-2 rounded-xl border-2 border-slate-100 text-[10px] font-black text-[#0F172A] uppercase tracking-widest hover:bg-[#0F172A] hover:text-white hover:border-[#0F172A] transition-all">
                                        Editar
                                    </a>
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-8 py-20 text-center">
                                <p class="text-slate-400 font-bold uppercase tracking-widest text-xs">No hay planes configurados</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($plans->hasPages())
            <div class="px-8 py-5 border-t border-slate-100 bg-slate-50/30">
                {{ $plans->links() }}
            </div>
        @endif

    </div>
</div>

@endsection
