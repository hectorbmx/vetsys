@extends('layouts.client')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-8">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-[#38B2AC]">Panel General</p>
            <h1 class="text-3xl md:text-4xl font-black text-[#0F172A] tracking-tight mt-1">
                Dashboard de la Clinica
            </h1>
            <p class="text-sm font-semibold text-slate-400 mt-2">
                Una lectura rapida de clientes, pacientes, ventas y caja.
            </p>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl px-5 py-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Hoy</p>
            <p class="text-sm font-black text-[#0F172A] mt-1">{{ now()->format('d/m/Y') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="relative overflow-hidden rounded-[24px] bg-[#0F172A] p-6 min-h-[190px] shadow-xl shadow-slate-200">
            <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full bg-[#38B2AC]/30"></div>
            <div class="absolute right-8 bottom-8 w-16 h-16 rounded-full bg-fuchsia-400/20"></div>
            <div class="relative z-10 h-full flex flex-col justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-300">Clientes</p>
                    <div class="text-5xl font-black text-white mt-4">{{ number_format($totalCustomers) }}</div>
                </div>
                <div class="flex items-center justify-between pt-6">
                    <span class="text-xs font-bold text-slate-300">Activos</span>
                    <span class="text-sm font-black text-white bg-white/10 px-3 py-1.5 rounded-full">{{ number_format($activeCustomers) }}</span>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-[24px] bg-gradient-to-br from-[#38B2AC] to-emerald-400 p-6 min-h-[190px] shadow-xl shadow-teal-100">
            <div class="absolute -right-8 -bottom-8 w-32 h-32 rounded-full bg-white/20"></div>
            <div class="relative z-10 h-full flex flex-col justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-white/80">Mascotas</p>
                    <div class="text-5xl font-black text-white mt-4">{{ number_format($totalAnimals) }}</div>
                </div>
                <div class="flex items-center justify-between pt-6">
                    <span class="text-xs font-bold text-white/80">Activas</span>
                    <span class="text-sm font-black text-white bg-white/20 px-3 py-1.5 rounded-full">{{ number_format($activeAnimals) }}</span>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-[24px] bg-gradient-to-br from-violet-600 to-fuchsia-500 p-6 min-h-[190px] shadow-xl shadow-fuchsia-100">
            <div class="absolute -left-10 -bottom-10 w-36 h-36 rounded-full bg-white/15"></div>
            <div class="relative z-10 h-full flex flex-col justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-white/80">Notas</p>
                    <div class="text-5xl font-black text-white mt-4">{{ number_format($totalNotes) }}</div>
                </div>
                <div class="grid grid-cols-2 gap-2 pt-6">
                    <div class="bg-white/15 rounded-xl px-3 py-2">
                        <p class="text-[10px] font-bold text-white/75">Pagadas</p>
                        <p class="text-sm font-black text-white">{{ number_format($paidNotes) }}</p>
                    </div>
                    <div class="bg-white/15 rounded-xl px-3 py-2">
                        <p class="text-[10px] font-bold text-white/75">Pendientes</p>
                        <p class="text-sm font-black text-white">{{ number_format($pendingNotes) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-[24px] bg-gradient-to-br from-amber-400 to-rose-500 p-6 min-h-[190px] shadow-xl shadow-rose-100">
            <div class="absolute right-0 top-0 w-28 h-28 rounded-bl-full bg-white/20"></div>
            <div class="relative z-10 h-full flex flex-col justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-white/80">Ingresos</p>
                    <div class="text-4xl font-black text-white mt-4">${{ number_format($totalCollected, 2) }}</div>
                </div>
                <div class="space-y-2 pt-5">
                    <div class="flex items-center justify-between text-xs font-bold text-white/85">
                        <span>Vendido</span>
                        <span>${{ number_format($totalSold, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs font-bold text-white/85">
                        <span>Por cobrar</span>
                        <span>${{ number_format($totalReceivable, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Notas Recientes</h2>
                    <p class="text-[11px] font-semibold text-slate-400 mt-1">Ultimos movimientos de venta registrados.</p>
                </div>
                <a href="{{ route('client.ventas.index') }}" class="text-[10px] font-black uppercase tracking-widest text-[#38B2AC] hover:text-[#0F172A]">Ver todas</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/70 border-b border-slate-100">
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Folio</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Cliente</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentNotes as $note)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-6 py-4 text-xs font-black text-[#0F172A]">{{ $note->folio }}</td>
                                <td class="px-6 py-4 text-xs font-bold text-slate-500">{{ $note->customer->full_name ?? 'Sin cliente' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest {{ $note->status === 'PAGADA' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ $note->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs font-black text-[#0F172A] text-right">${{ number_format($note->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-xs font-bold text-slate-400">Todavia no hay notas registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm p-6 space-y-5">
            <div>
                <h2 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Venta vs Cobro</h2>
                <p class="text-[11px] font-semibold text-slate-400 mt-1">Diferencia entre lo generado y lo que entro a caja.</p>
            </div>

            @php
                $collectionPercent = $totalSold > 0 ? min(($totalCollected / $totalSold) * 100, 100) : 0;
            @endphp

            <div class="space-y-3">
                <div class="flex items-end justify-between">
                    <span class="text-xs font-bold text-slate-500">Cobrado</span>
                    <span class="text-2xl font-black text-[#0F172A]">{{ number_format($collectionPercent, 1) }}%</span>
                </div>
                <div class="h-4 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-[#38B2AC] to-emerald-400 rounded-full" style="width: {{ $collectionPercent }}%"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-2">
                <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Vendido</p>
                    <p class="text-lg font-black text-[#0F172A] mt-2">${{ number_format($totalSold, 2) }}</p>
                </div>
                <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-emerald-600">Cobrado</p>
                    <p class="text-lg font-black text-emerald-700 mt-2">${{ number_format($totalCollected, 2) }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
