@extends('layouts.client')

@section('title', 'Dashboard')
@section('contextual-tour', 'dashboard')

@section('content')
<div class="space-y-8">
    <div data-tour="dashboard-welcome" class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.28em] theme-text-primary">Panel General</p>
            <h1 class="text-3xl md:text-4xl font-black theme-text-heading tracking-tight mt-1">
                Dashboard de la Clinica
            </h1>
            <p class="text-sm font-semibold text-slate-400 mt-2">
                Una lectura rapida de clientes, pacientes, ventas y caja.
            </p>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl px-5 py-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Hoy</p>
            <p class="text-sm font-black theme-text-heading mt-1">{{ now()->format('d/m/Y') }}</p>
        </div>
    </div>

    @if($onboarding)
        @if(!$onboarding['is_completed'])
            <section data-tour="operational-onboarding" class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="grid grid-cols-1 xl:grid-cols-[320px_1fr]">
                    <div class="relative overflow-hidden theme-surface-dark p-7">
                        <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full theme-bg-primary-soft-hover"></div>
                        <div class="absolute -bottom-16 -left-10 h-44 w-44 rounded-full bg-violet-500/15"></div>

                        <div class="relative z-10">
                            <p class="text-[10px] font-black uppercase tracking-[0.28em] theme-text-primary">Ruta hacia tu primera venta</p>
                            <h2 class="mt-3 text-2xl font-black tracking-tight">{{ $onboarding['completed'] }} de {{ $onboarding['total'] }} completados</h2>
                            <p class="mt-2 text-xs font-semibold leading-5 text-slate-300">
                                Completa el camino minimo para registrar tu primera venta.
                            </p>

                            <div class="mt-6 h-3 overflow-hidden rounded-full bg-white/10">
                                <div class="h-full rounded-full theme-progress-primary transition-all"
                                     style="width: {{ $onboarding['percentage'] }}%"></div>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-[10px] font-black uppercase tracking-widest">
                                <span class="text-slate-400">Progreso</span>
                                <span class="text-white">{{ $onboarding['percentage'] }}%</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 divide-y divide-slate-100 md:grid-cols-2 md:divide-x md:divide-y-0">
                        @foreach(array_chunk($onboarding['steps'], 3) as $stepColumn)
                            <div class="divide-y divide-slate-100">
                                @foreach($stepColumn as $step)
                                    <div class="flex items-start gap-4 p-5 {{ $step['is_next'] ? 'theme-bg-primary-soft' : '' }}">
                                        <div class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2 text-sm font-black
                                            {{ $step['completed'] ? 'border-emerald-500 bg-emerald-500 text-white' : ($step['is_next'] ? 'theme-border-primary bg-white theme-text-primary' : 'border-slate-200 bg-slate-50 text-slate-300') }}">
                                            @if($step['completed'])
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                </svg>
                                            @else
                                                {{ $loop->parent->index * 3 + $loop->iteration }}
                                            @endif
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <h3 class="text-xs font-black {{ $step['completed'] ? 'text-slate-400 line-through' : 'theme-text-heading' }}">
                                                    {{ $step['label'] }}
                                                </h3>
                                                @if($step['is_next'])
                                                    <span class="rounded-full theme-bg-primary-soft px-2 py-1 text-[8px] font-black uppercase tracking-widest theme-text-primary-strong">Siguiente</span>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-[10px] font-semibold leading-4 text-slate-400">{{ $step['description'] }}</p>

                                            @if(!$step['completed'])
                                                <a href="{{ $step['action_url'] }}"
                                                   class="mt-3 inline-flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest {{ $step['is_next'] ? 'theme-text-primary-strong' : 'text-slate-400 theme-hover-text-primary-strong' }}">
                                                    {{ $step['action_label'] }}
                                                    <span aria-hidden="true">&rarr;</span>
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @else
            <section data-tour="operational-onboarding" class="flex flex-col gap-4 rounded-[24px] border border-emerald-200 bg-gradient-to-r from-emerald-50 to-white px-6 py-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg shadow-emerald-200">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-emerald-600">Ruta inicial completa</p>
                        <h2 class="mt-1 text-sm font-black theme-text-heading">Tu clinica ya esta lista para continuar vendiendo.</h2>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-xs font-black text-emerald-700">6 de 6 completados</span>
                    <form method="POST" action="{{ route('client.dashboard.onboarding-banner.dismiss') }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="rounded-full border border-emerald-200 bg-white px-3 py-2 text-[9px] font-black uppercase tracking-widest text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-50">
                            Quitar banner
                        </button>
                    </form>
                </div>
            </section>
        @endif
    @endif

    <div data-tour="dashboard-metrics" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <a href="{{ route('client.customers.index') }}" class="relative overflow-hidden rounded-[24px] theme-surface-dark p-6 min-h-[190px] shadow-xl shadow-slate-200 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl theme-focus-primary">
            <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full theme-bg-primary-soft"></div>
            <div class="absolute right-8 bottom-8 w-16 h-16 rounded-full bg-white/10"></div>
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
        </a>

        <a href="{{ route('client.animals.index') }}" class="relative overflow-hidden rounded-[24px] theme-gradient-primary p-6 min-h-[190px] shadow-xl shadow-teal-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl theme-focus-primary">
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
        </a>

        <a href="{{ route('client.ventas.index') }}" class="relative overflow-hidden rounded-[24px] theme-gradient-primary p-6 min-h-[190px] shadow-xl shadow-teal-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl theme-focus-primary">
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
        </a>

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
        <div data-tour="recent-sales" class="xl:col-span-2 bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-black theme-text-heading uppercase tracking-widest">Notas Recientes</h2>
                    <p class="text-[11px] font-semibold text-slate-400 mt-1">Ultimos movimientos de venta registrados.</p>
                </div>
                <a href="{{ route('client.ventas.index') }}" class="text-[10px] font-black uppercase tracking-widest theme-link-primary">Ver todas</a>
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
                                <td class="px-6 py-4 text-xs font-black theme-text-heading">{{ $note->folio }}</td>
                                <td class="px-6 py-4 text-xs font-bold text-slate-500">{{ $note->customer->full_name ?? 'Sin cliente' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest {{ $note->status === 'PAGADA' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ $note->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs font-black theme-text-heading text-right">${{ number_format($note->total, 2) }}</td>
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
                <h2 class="text-sm font-black theme-text-heading uppercase tracking-widest">Venta vs Cobro</h2>
                <p class="text-[11px] font-semibold text-slate-400 mt-1">Diferencia entre lo generado y lo que entro a caja.</p>
            </div>

            @php
                $collectionPercent = $totalSold > 0 ? min(($totalCollected / $totalSold) * 100, 100) : 0;
            @endphp

            <div class="space-y-3">
                <div class="flex items-end justify-between">
                    <span class="text-xs font-bold text-slate-500">Cobrado</span>
                    <span class="text-2xl font-black theme-text-heading">{{ number_format($collectionPercent, 1) }}%</span>
                </div>
                <div class="h-4 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full theme-progress-primary rounded-full" style="width: {{ $collectionPercent }}%"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-2">
                <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Vendido</p>
                    <p class="text-lg font-black theme-text-heading mt-2">${{ number_format($totalSold, 2) }}</p>
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
