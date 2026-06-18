@extends('layouts.client')

@section('title', 'Inventario')

@section('content')
<div class="p-6 max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.24em] theme-text-primary">Catalogo</p>
            <h1 class="text-2xl font-black theme-text-heading tracking-tighter">Inventario</h1>
            <p class="text-xs text-slate-400 font-semibold mt-1">Productos que manejan existencias y su ultimo movimiento.</p>
        </div>
        <a href="{{ route('client.servicios.index') }}" class="theme-button-dark px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] text-center">
            Volver al catalogo
        </a>
    </div>

    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/70">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Producto</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Stock</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Venta sin stock</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Ultimo movimiento</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($items as $item)
                        @php
                            $stock = (float) ($item->inventory?->stock_actual ?? 0);
                            $minimum = (float) ($item->inventory?->stock_minimo ?? 0);
                            $statusLabel = 'Normal';
                            $statusClass = 'bg-emerald-50 text-emerald-600';

                            if ($stock < 0) {
                                $statusLabel = 'Negativo';
                                $statusClass = 'bg-rose-50 text-rose-600';
                            } elseif ($stock == 0.0) {
                                $statusLabel = 'Agotado';
                                $statusClass = 'bg-amber-50 text-amber-600';
                            } elseif ($stock <= $minimum) {
                                $statusLabel = 'Bajo';
                                $statusClass = 'bg-orange-50 text-orange-600';
                            }

                            $lastMovement = $item->inventory?->movements->first();
                        @endphp
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <p class="text-xs font-black theme-text-heading">{{ $item->name }}</p>
                                <p class="text-[10px] text-slate-400 font-mono mt-1">{{ $item->sku }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-black {{ $stock <= $minimum ? 'text-rose-600' : 'theme-text-heading' }}">{{ number_format($stock, 2) }}</p>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Min: {{ number_format($minimum, 2) }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <form action="{{ route('client.servicios.toggle-negative-stock', $item) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="flex items-center gap-2 group focus:outline-none"
                                            title="{{ $item->inventory?->allow_negative_stock ? 'Click para bloquear venta sin stock' : 'Click para permitir venta sin stock' }}">
                                        <div class="w-10 h-6 flex items-center p-1 rounded-full transition-colors duration-300 {{ $item->inventory?->allow_negative_stock ? 'bg-rose-500' : 'bg-slate-300' }}">
                                            <div class="w-4 h-4 bg-white rounded-full shadow-sm transition-transform duration-300 transform {{ $item->inventory?->allow_negative_stock ? 'translate-x-4' : 'translate-x-0' }}"></div>
                                        </div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider {{ $item->inventory?->allow_negative_stock ? 'text-rose-600' : 'text-slate-400' }}">
                                            {{ $item->inventory?->allow_negative_stock ? 'Permitida' : 'Bloqueada' }}
                                        </span>
                                    </button>
                                </form>
                            </td>
                            <td class="px-6 py-4">
                                @if($lastMovement)
                                    <p class="text-xs font-bold theme-text-heading">{{ ucfirst(str_replace('_', ' ', $lastMovement->type)) }}</p>
                                    <p class="text-[10px] text-slate-400 font-semibold">{{ $lastMovement->occurred_at->format('d/m/Y H:i') }}</p>
                                @else
                                    <span class="text-[11px] text-slate-300 italic">Sin movimientos</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('client.servicios.show', $item) }}" class="p-1.5 text-slate-400 theme-hover-text-primary transition-colors text-xl leading-none" title="Ver Kardex">🔍</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm font-bold text-slate-400">
                                No hay productos con control de inventario.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
