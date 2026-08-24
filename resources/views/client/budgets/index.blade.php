@extends('layouts.client')

@section('title', 'Presupuestos')

@section('content')
@php
    $showKpiCards = \App\Support\TenantKpiVisibility::isVisible(auth()->user()?->tenant, \App\Support\TenantKpiVisibility::BUDGETS_INDEX);
@endphp
<div class="-mt-4 space-y-6">
    @if($showKpiCards)
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="group theme-surface-dark relative overflow-hidden rounded-[24px] border border-slate-900 p-6 shadow-xl transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl">
            <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full theme-bg-primary-soft"></div>
            <div class="relative z-10 flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Presupuestos del mes</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black tracking-tight text-white">{{ $totalBudgetsMonth }}</span>
                        <span class="text-[10px] font-medium uppercase text-slate-300">generados</span>
                    </div>
                    <p class="mt-2 text-[10px] font-bold uppercase text-slate-300">{{ $openBudgets }} abiertos</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-xl text-white transition-transform group-hover:scale-110">&#128196;</div>
            </div>
        </div>

        <div class="group theme-gradient-primary theme-border-primary relative overflow-hidden rounded-[24px] p-6 shadow-xl transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl">
            <div class="absolute -right-8 -bottom-8 h-32 w-32 rounded-full bg-white/20"></div>
            <div class="relative z-10 flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/80">Aceptados del mes</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black tracking-tight text-white">{{ $acceptedBudgetsMonth }}</span>
                        <span class="text-[10px] font-medium uppercase text-white/80">presupuestos</span>
                    </div>
                    <p class="mt-2 text-[10px] font-bold uppercase text-white/80">${{ number_format($acceptedTotalMonth, 2) }} aceptado</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20 text-xl text-white transition-transform group-hover:scale-110">&#10003;</div>
            </div>
        </div>

        <div class="group theme-bg-primary-soft theme-border-primary-soft relative overflow-hidden rounded-[24px] border p-6 shadow-xl transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl">
            <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/15"></div>
            <div class="relative z-10 flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <p class="text-[10px] font-black uppercase tracking-widest theme-text-primary-strong">Por vencer</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black tracking-tight theme-text-heading">{{ $expiringSoon }}</span>
                        <span class="text-[10px] font-medium theme-text-primary-strong">en 7 dias</span>
                    </div>
                    <p class="mt-2 text-[10px] font-bold uppercase theme-text-primary-strong">Borradores y enviados</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20 text-xl text-white transition-transform group-hover:scale-110">&#9201;</div>
            </div>
        </div>
    </div>
    @endif

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="space-y-3 border-b border-slate-100 bg-slate-50/50 p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-black tracking-tighter theme-text-heading">Presupuestos</h1>
                    <p class="mt-1 text-xs font-bold uppercase tracking-widest text-slate-400">Cotizaciones por cliente, caballo y servicios.</p>
                </div>
                <div class="flex flex-col items-start gap-3 sm:items-end">
                    <form method="GET" action="{{ route('client.budgets.index') }}" class="flex items-center gap-2">
                        @if($search !== '')
                            <input type="hidden" name="q" value="{{ $search }}">
                        @endif
                        <label for="budgets-per-page" class="text-[10px] font-black uppercase tracking-widest text-slate-400">Mostrar</label>
                        <select id="budgets-per-page" name="per_page" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold theme-text-heading outline-none theme-input">
                            @foreach([15, 30, 50, 100] as $option)
                                <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        <span class="text-[10px] font-bold text-slate-400">filas</span>
                    </form>

                    <a href="{{ route('client.budgets.create') }}" class="inline-flex items-center justify-center gap-2 theme-surface-dark px-5 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] shadow-lg theme-shadow-primary hover:shadow-xl hover:-translate-y-0.5 theme-hover-bg-primary transition-all duration-300 whitespace-nowrap">
                        <span class="flex h-4 w-4 items-center justify-center rounded-full theme-bg-primary text-xs font-black text-white">+</span>
                        Nuevo presupuesto
                    </a>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" action="{{ route('client.budgets.index') }}" class="relative w-full sm:max-w-md">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-xs text-slate-400">&#128269;</span>
                    <input type="text" name="q" value="{{ $search }}" placeholder="Buscar folio, cliente, correo o telefono..." class="w-full rounded-xl border border-slate-200 bg-white py-3.5 pl-10 pr-12 text-xs font-semibold theme-text-heading shadow-sm outline-none transition-all placeholder-slate-400 theme-input focus:ring-4 theme-ring-primary">
                    @if($search !== '')
                        <a href="{{ route('client.budgets.index', ['per_page' => $perPage]) }}" class="absolute inset-y-0 right-0 flex items-center pr-4 text-xs font-black text-slate-400 hover:text-rose-500">x</a>
                    @endif
                </form>
                @if($search !== '')
                    <span class="text-[11px] font-bold text-slate-400">Filtro: {{ $search }}</span>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="theme-surface-dark">
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-white">Folio</th>
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-white">Cliente</th>
                        <th class="px-6 py-4 text-center text-[10px] font-black uppercase tracking-widest text-white">Caballos</th>
                        <th class="px-6 py-4 text-center text-[10px] font-black uppercase tracking-widest text-white">Servicios</th>
                        <th class="px-6 py-4 text-right text-[10px] font-black uppercase tracking-widest text-white">Total</th>
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-white">Vigencia</th>
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-white">Estatus</th>
                        <th class="px-6 py-4 text-right text-[10px] font-black uppercase tracking-widest text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($budgets as $budget)
                        @php
                            $statusClasses = [
                                \App\Models\Budget::STATUS_DRAFT => 'bg-slate-100 text-slate-500',
                                \App\Models\Budget::STATUS_SENT => 'bg-sky-50 text-sky-700',
                                \App\Models\Budget::STATUS_ACCEPTED => 'bg-emerald-50 text-emerald-700',
                                \App\Models\Budget::STATUS_REJECTED => 'bg-rose-50 text-rose-700',
                                \App\Models\Budget::STATUS_EXPIRED => 'bg-amber-50 text-amber-700',
                            ][$budget->status] ?? 'bg-slate-100 text-slate-500';
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50/60">
                            <td class="px-6 py-4">
                                <p class="text-xs font-mono font-black theme-text-heading">{{ $budget->folio }}</p>
                                <p class="mt-1 text-[10px] font-semibold text-slate-400">{{ $budget->budget_date?->format('d/m/Y') }}</p>
                            </td>
                            <td class="px-6 py-4">
                                @if($budget->customer)
                                    <a href="{{ route('client.customers.show', $budget->customer) }}" class="block text-xs font-black theme-text-heading theme-hover-text-primary hover:underline">
                                        {{ $budget->customer->full_name }}
                                    </a>
                                    <span class="mt-1 block text-[10px] font-semibold text-slate-400">{{ $budget->customer->phone ?? $budget->customer->email ?? 'Sin contacto' }}</span>
                                @else
                                    <span class="text-xs font-bold text-slate-400">Cliente no disponible</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex rounded-full theme-bg-primary-soft px-2.5 py-1 text-[10px] font-black theme-text-primary">{{ $budget->animals_count }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-500">{{ $budget->items_count }}</span>
                            </td>
                            <td class="px-6 py-4 text-right text-xs font-black theme-text-heading">${{ number_format((float) $budget->total, 2) }}</td>
                            <td class="px-6 py-4">
                                <p class="text-xs font-bold text-slate-600">{{ $budget->valid_until?->format('d/m/Y') ?? 'Sin vigencia' }}</p>
                                @if($budget->valid_until && $budget->valid_until->isPast() && ! in_array($budget->status, [\App\Models\Budget::STATUS_ACCEPTED, \App\Models\Budget::STATUS_REJECTED], true))
                                    <p class="mt-1 text-[10px] font-bold text-amber-600">Vencido</p>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-widest {{ $statusClasses }}">
                                    {{ $statusLabels[$budget->status] ?? $budget->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('client.budgets.show', $budget) }}" class="p-1.5 text-slate-400 theme-hover-text-primary transition-colors" title="Ver presupuesto">&#128269;</a>
                                    <a href="{{ route('client.budgets.pdf', $budget) }}" target="_blank" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600 transition-colors hover:bg-rose-100 hover:text-rose-700" title="PDF presupuesto">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M6 2h8l4 4v16H6V2Zm7 1.5V7h3.5L13 3.5ZM8.5 12.5h1.25c1.2 0 2 .7 2 1.75s-.8 1.75-2 1.75H9v2H7.5v-5.5h1Zm.5 2.25h.65c.38 0 .62-.18.62-.5s-.24-.5-.62-.5H9v1Zm3.25-2.25h1.4c1.72 0 2.85 1.03 2.85 2.75S15.37 18 13.65 18h-1.4v-5.5Zm1.5 4.2c.78-.02 1.25-.52 1.25-1.45s-.47-1.43-1.25-1.45v2.9Zm3.25-4.2h3.5v1.3H18.5v.9h1.75V16H18.5v2H17v-5.5Z"/>
                                        </svg>
                                    </a>
                                    <form action="{{ route('client.budgets.destroy', $budget) }}" method="POST" onsubmit="return confirm('Eliminar este presupuesto? Esta accion no se puede deshacer.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600 transition-colors hover:bg-rose-100 hover:text-rose-700" title="Eliminar presupuesto">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M3 6h18"/>
                                                <path d="M8 6V4h8v2"/>
                                                <path d="M19 6l-1 14H6L5 6"/>
                                                <path d="M10 11v5"/>
                                                <path d="M14 11v5"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <p class="text-sm font-black theme-text-heading">Sin presupuestos registrados</p>
                                <p class="mt-2 text-xs font-semibold text-slate-400">Crea el primer presupuesto para un cliente y sus caballos.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 px-5 py-4">
            <div class="dynamic-pagination max-w-full overflow-x-auto">
                {{ $budgets->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
