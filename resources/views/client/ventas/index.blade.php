@extends('layouts.client')

@section('content')
@php
    $showKpiCards = \App\Support\TenantKpiVisibility::isVisible(auth()->user()?->tenant, \App\Support\TenantKpiVisibility::VENTAS_INDEX);
@endphp
<div class="p-6 max-w-7xl mx-auto space-y-6">

    {{-- ENCABEZADO --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black theme-text-heading tracking-tighter">
                {{ $usesMonthlyCutoffBilling ? 'Historial de Servicios' : 'Historial de Ventas' }}
            </h1>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">
                {{ $usesMonthlyCutoffBilling ? 'Consulta los servicios realizados que alimentan las cuentas mensuales.' : 'Monitorea los folios emitidos, estados de cuenta de clientes y cuentas por cobrar.' }}
            </p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
            <a href="{{ route('client.ventas.create') }}" class="inline-flex items-center justify-center gap-2 theme-surface-dark px-5 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg shadow-slate-200 transition-all whitespace-nowrap">
                + Nueva Nota de Venta
            </a>
        </div>
    </div>

    @if($showKpiCards)
    {{-- CARDS / TRES KPIS SUPERIORES --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        {{-- KPI 1: VENTAS DEL MES (VIOLETA) --}}
        <div class="group theme-surface-dark border border-slate-900 rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full theme-bg-primary-soft"></div>
            <div class="absolute right-8 bottom-8 w-16 h-16 rounded-full bg-white/10"></div>
            <div class="relative z-10 flex items-center justify-between w-full">
                <div class="space-y-1">
                    <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest">{{ $usesMonthlyCutoffBilling ? 'Cargos del Mes' : 'Ventas del Mes' }}</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-white tracking-tight">${{ number_format($totalSalesMonth, 2) }}</span>
                    </div>
                    @if($totalPending > 0)
                        <div class="flex items-center gap-1.5 mt-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400 animate-pulse"></span>
                            <p class="text-[10px] font-bold text-slate-300 uppercase">Adeudo total pendiente: ${{ number_format($totalPending, 2) }}</p>
                        </div>
                    @else
                        <p class="text-[10px] font-bold text-emerald-300 uppercase mt-2">✓ Cartera al día</p>
                    @endif
                </div>
                <div class="w-12 h-12 rounded-2xl bg-white/10 text-white flex items-center justify-center text-xl group-hover:scale-110 transition-transform">💰</div>
            </div>
        </div>

        {{-- KPI 2: NOTAS GENERADAS (AMBAR) --}}
        <div class="group theme-gradient-primary theme-border-primary rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl relative overflow-hidden">
            <div class="absolute -right-8 -bottom-8 w-32 h-32 rounded-full bg-white/20"></div>
            <div class="absolute -left-4 -top-4 w-20 h-20 rounded-full bg-white/10"></div>
            <div class="relative z-10 flex items-center justify-between w-full">
                <div class="space-y-1">
                    <p class="text-[10px] font-black text-white/80 uppercase tracking-widest">{{ $usesMonthlyCutoffBilling ? 'Servicios Realizados' : 'Notas Generadas' }}</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-white tracking-tight">{{ $totalNotesMonth }}</span>
                        <span class="text-[10px] font-medium text-white/80">{{ $usesMonthlyCutoffBilling ? 'cargos este mes' : 'este mes' }}</span>
                    </div>
                    @if($usesMonthlyCutoffBilling)
                        <p class="text-[10px] font-bold text-white/80 uppercase mt-2">Agrupados despues por corte mensual</p>
                    @else
                        <div class="flex gap-3 mt-2">
                            <div class="flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                <span class="text-[9px] font-bold text-white uppercase">{{ $paidNotesMonth }} Pagadas</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                                <span class="text-[9px] font-bold text-white uppercase">{{ $pendingNotesMonth }} Pendientes</span>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="w-12 h-12 rounded-2xl bg-white/20 text-white flex items-center justify-center text-xl group-hover:scale-110 transition-transform">📄</div>
            </div>
        </div>

        {{-- KPI 3: PACIENTES ATENDIDOS (TURQUESA) --}}
        <div class="group theme-bg-primary-soft border theme-border-primary-soft rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full bg-white/15"></div>
            <div class="absolute left-8 bottom-8 w-16 h-16 rounded-full bg-white/10"></div>
            <div class="relative z-10 flex items-center justify-between w-full">
                <div class="space-y-1">
                    <p class="text-[10px] font-black theme-text-primary-strong uppercase tracking-widest">Pacientes Atendidos Este Mes</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black theme-text-heading tracking-tight">{{ $animalsAttendedMonth }}</span>
                        <span class="text-[10px] font-medium theme-text-primary-strong">animales únicos</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-white/20 text-white flex items-center justify-center text-xl group-hover:scale-110 transition-transform">🐾</div>
            </div>
        </div>
    </div>

    @endif

    {{-- TABLA HISTÓRICA --}}
    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50 space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">{{ $usesMonthlyCutoffBilling ? 'Servicios realizados' : 'Notas registradas' }}</h3>
                <form method="GET" action="{{ route('client.ventas.index') }}" class="flex items-center gap-2">
                    @if(request()->filled('q'))
                        <input type="hidden" name="q" value="{{ request('q') }}">
                    @endif
                    <label for="sales-per-page" class="text-[10px] font-black uppercase tracking-widest text-slate-400">Mostrar</label>
                    <select id="sales-per-page" name="per_page" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold theme-text-heading outline-none theme-input">
                        @foreach([15, 30, 50, 100] as $option)
                            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <span class="text-[10px] font-bold text-slate-400">filas</span>
                </form>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" action="{{ route('client.ventas.index') }}" class="relative w-full sm:max-w-md">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 text-xs">🔍</span>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ $usesMonthlyCutoffBilling ? 'Buscar servicio, paciente, cliente o folio...' : 'Buscar folio, cliente o telefono...' }}" class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-12 py-3.5 text-xs font-semibold theme-text-heading placeholder-slate-400 theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-sm">
                    @if(request()->filled('q'))
                        <a href="{{ route('client.ventas.index', ['per_page' => $perPage]) }}" class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-rose-500 text-xs font-black">x</a>
                    @endif
                </form>
                @if(request()->filled('q'))
                    <span class="text-[11px] font-bold text-slate-400">Filtro: {{ request('q') }}</span>
                @endif
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                @if($usesMonthlyCutoffBilling)
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Fecha</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Cliente</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Paciente</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Servicio / Producto</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Cantidad</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Precio</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Subtotal</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Referencia</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($serviceDetails as $detail)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4 text-xs font-semibold text-slate-600">
                                    {{ $detail->note?->date_at?->format('d/m/Y') ?? '--' }}
                                </td>
                                <td class="px-6 py-4">
                                    @if($detail->note?->customer)
                                        <a href="{{ route('client.customers.show', $detail->note->customer) }}"
                                           class="text-xs font-bold theme-text-heading block theme-hover-text-primary hover:underline transition-colors">
                                            {{ $detail->note->customer->full_name }}
                                        </a>
                                        <span class="text-[10px] text-slate-400 font-medium block">{{ $detail->note->customer->phone ?? 'Sin telefono' }}</span>
                                    @else
                                        <span class="text-xs font-bold text-slate-400">Cliente no disponible</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($detail->animal)
                                        <a href="{{ route('client.animals.edit', $detail->animal) }}"
                                           class="text-xs font-bold theme-text-heading block theme-hover-text-primary hover:underline transition-colors">
                                            {{ $detail->animal->name }}
                                        </a>
                                    @else
                                        <span class="text-xs font-bold text-slate-400">Sin paciente</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-xs font-black theme-text-heading block">{{ $detail->catalogItem->name ?? 'Servicio eliminado' }}</span>
                                    <span class="text-[10px] text-slate-400 font-semibold">{{ ucfirst($detail->catalogItem->type ?? 'cargo') }}</span>
                                </td>
                                <td class="px-6 py-4 text-right text-xs font-bold theme-text-heading">{{ number_format((float) $detail->quantity, 2) }}</td>
                                <td class="px-6 py-4 text-right text-xs font-bold text-slate-600">${{ number_format((float) $detail->price_at_sale, 2) }}</td>
                                <td class="px-6 py-4 text-right text-xs font-black theme-text-heading">${{ number_format((float) $detail->subtotal, 2) }}</td>
                                <td class="px-6 py-4 text-right">
                                    @if($detail->note)
                                        <a href="{{ route('client.ventas.show', $detail->note) }}"
                                           class="text-[10px] font-mono font-black theme-text-primary hover:underline">
                                            {{ $detail->note->folio }}
                                        </a>
                                    @else
                                        <span class="text-[10px] font-bold text-slate-400">--</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-sm font-bold text-slate-400">
                                    No se han registrado servicios para los criterios seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                @else
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/50">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Folio</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Fecha</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Cliente / Propietario</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Monto Total</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Saldo Pendiente</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($notes as $note)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            {{-- Folio --}}
                            <td class="px-6 py-4 text-xs font-mono font-bold theme-text-heading">
                                {{ $note->folio }}
                            </td>

                            {{-- Fecha --}}
                            <td class="px-6 py-4 text-xs font-semibold text-slate-600">
                                {{ $note->date_at->format('d/m/Y') }}
                            </td>

                            {{-- Cliente --}}
                            {{-- <td class="px-6 py-4">
                                <span class="text-xs font-bold theme-text-heading block">{{ $note->customer->full_name }}</span>
                                <span class="text-[10px] text-slate-400 font-medium block">📞 {{ $note->customer->phone ?? 'Sin Teléfono' }}</span>
                            </td> --}}
                            {{-- Cliente --}}
                            <td class="px-6 py-4">
                                <a href="{{ route('client.customers.show', $note->customer->id) }}" 
                                class="text-xs font-bold theme-text-heading block theme-hover-text-primary hover:underline transition-colors">
                                    {{ $note->customer->full_name }}
                                </a>
                                <span class="text-[10px] text-slate-400 font-medium block">📞 {{ $note->customer->phone ?? 'Sin Teléfono' }}</span>
                            </td>

                            {{-- Total --}}
                            <td class="px-6 py-4 text-xs font-black theme-text-heading">
                                ${{ number_format($note->total, 2) }}
                            </td>

                            {{-- Saldo Pendiente --}}
                            
                            <td class="px-6 py-4 text-xs font-bold">
                                @if($note->balance > 0)
                                    <span class="text-rose-600">${{ number_format($note->balance, 2) }}</span>
                                @else
                                    <span class="text-slate-400 font-medium">$0.00</span>
                                @endif
                            </td>

                            {{-- Estado Badge --}}
                            <td class="px-6 py-4">
                                @if($note->status === 'PAGADA')
                                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">
                                        🟢 Pagada
                                    </span>
                                @elseif($note->status === 'PENDIENTE')
                                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full">
                                        🟡 Crédito / Pendiente
                                    </span>
                                @else
                                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">
                                        ⚪ Cancelada
                                    </span>
                                @endif
                            </td>

                            {{-- Acciones --}}
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('client.ventas.show', $note->id) }}"
                                        class="p-1.5 text-slate-400 theme-hover-text-primary transition-colors"
                                        title="Ver ficha">&#128269;</a>

                                    @if(($note->payments_count ?? 0) === 0)
                                        <a href="{{ route('client.ventas.edit', $note) }}"
                                            class="p-1.5 text-slate-400 hover:text-amber-600 transition-colors"
                                            title="Editar nota">&#9998;</a>

                                        <form action="{{ route('client.ventas.destroy', $note) }}" method="POST" class="inline" onsubmit="return confirm('Eliminar esta nota? Esta accion no se puede deshacer.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 transition-colors" title="Eliminar nota">
                                                &#128465;
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-sm font-bold text-slate-400">
                                No se han registrado notas de venta en este periodo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @endif
            </table>
        </div>

        <div class="p-6 border-t border-slate-100 bg-slate-50/30">
            {{ $usesMonthlyCutoffBilling ? $serviceDetails->links() : $notes->links() }}
        </div>
    </div>

</div>
@endsection
