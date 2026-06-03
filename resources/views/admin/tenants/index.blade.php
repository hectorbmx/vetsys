@extends('layouts.admin')

@section('title', 'Clientes')

@section('content')

<div class="space-y-8">

    {{-- Header Section --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-4xl font-black text-[#0F172A] tracking-tight">Clientes</h1>
            <p class="text-slate-500 font-medium mt-1">
                Administración central de clínicas y unidades veterinarias en el ecosistema SaaS.
            </p>
        </div>

        <a href="{{ route('admin.tenants.create') }}"
           class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#0F172A] px-6 py-3.5 text-sm font-black text-white hover:bg-slate-800 transition-all shadow-lg shadow-slate-200 group">
            <span class="text-lg group-hover:rotate-90 transition-transform">+</span>
            NUEVO CLIENTE
        </a>
    </div>

    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @php
            $stats = [
                ['label' => 'Total clientes', 'value' => $totalTenants, 'icon' => '👥', 'color' => 'bg-slate-50'],
                ['label' => 'Clientes activos', 'value' => $activeTenants, 'icon' => '●', 'color' => 'bg-emerald-50 text-emerald-600'],
                ['label' => 'Inactivos / Suspendidos', 'value' => $inactiveTenants, 'icon' => '○', 'color' => 'bg-red-50 text-red-600'],
            ];
        @endphp

        @foreach($stats as $stat)
        <div class="bg-white border border-slate-200 rounded-[24px] p-6 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">
                {{ $stat['icon'] }}
            </div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">{{ $stat['label'] }}</p>
            <p class="text-4xl font-black mt-2 text-[#0F172A] tracking-tighter">{{ $stat['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Table Section --}}
    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <h2 class="text-xs font-black text-slate-500 uppercase tracking-widest">Listado de Unidades</h2>
            <div class="flex gap-2">
                {{-- Botón de filtro minimalista --}}
                <button class="p-2 hover:bg-white rounded-lg border border-transparent hover:border-slate-200 transition-all text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm border-separate border-spacing-0">
                <thead>
                    <tr class="text-[#0F172A]">
                        <th class="px-6 py-4 text-left font-black uppercase tracking-widest text-[10px]">Cliente</th>
                        <th class="px-6 py-4 text-left font-black uppercase tracking-widest text-[10px]">Contacto</th>
                        <th class="px-6 py-4 text-left font-black uppercase tracking-widest text-[10px]">Plan</th>
                        <th class="px-6 py-4 text-left font-black uppercase tracking-widest text-[10px]">Estado</th>
                        <th class="px-6 py-4 text-right font-black uppercase tracking-widest text-[10px]">Gestión</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse($tenants as $tenant)
                        <tr class="hover:bg-slate-50/80 transition-colors group">
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center font-bold text-slate-400 group-hover:bg-[#38B2AC]/10 group-hover:text-[#38B2AC] transition-colors">
                                        {{ substr($tenant->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="font-black text-[#0F172A] text-base leading-tight">{{ $tenant->name }}</p>
                                        <p class="text-[11px] text-slate-400 font-medium uppercase tracking-tighter mt-0.5">{{ $tenant->business_name ?? 'Persona Física' }}</p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-5">
                                <p class="text-slate-600 font-medium">{{ $tenant->email ?? '---' }}</p>
                                <p class="text-xs text-slate-400">{{ $tenant->phone ?? 'Sin teléfono' }}</p>
                            </td>

                            <td class="px-6 py-5">
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 text-[11px] font-black text-blue-600 uppercase tracking-wide">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                                    {{ $tenant->plan->name ?? 'Básico' }}
                                </span>
                            </td>

                            <td class="px-6 py-5">
                                @if($tenant->status === 'active')
                                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#38B2AC]/10 px-2.5 py-1 text-[11px] font-black text-[#38B2AC] uppercase tracking-wide">
                                        ACTIVO
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-black text-slate-500 uppercase tracking-wide">
                                        INACTIVO
                                    </span>
                                @endif
                            </td>

                            <td class="px-6 py-5 text-right">
                                <a href="{{ route('admin.tenants.show', $tenant) }}"
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:bg-[#0F172A] hover:text-white transition-all">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center">
                                    <span class="text-4xl mb-4 opacity-20">📂</span>
                                    <p class="text-slate-400 font-bold">No se encontraron clientes registrados.</p>
                                    <p class="text-xs text-slate-300 uppercase tracking-widest mt-1">VetSys Database Empty</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tenants->hasPages())
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/30">
            {{ $tenants->links() }}
        </div>
        @endif
    </div>
</div>

@endsection