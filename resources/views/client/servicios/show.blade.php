@extends('layouts.client')

@section('title', 'Detalle de catalogo')

@section('content')
<div class="p-6 max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.24em] theme-text-primary">Detalle</p>
            <h1 class="text-2xl font-black theme-text-heading tracking-tighter">{{ $catalogItem->name }}</h1>
            <p class="text-xs text-slate-400 font-semibold mt-1">
                {{ $catalogItem->type === 'product' ? 'Producto' : 'Servicio' }} · {{ $catalogItem->sku }}
            </p>
        </div>
        <a href="{{ route('client.servicios.index') }}" class="theme-button-dark px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] text-center">
            Volver al catalogo
        </a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-3 rounded-2xl text-xs font-bold">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-5 py-3 rounded-2xl text-xs font-bold">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/70">
                    <h2 class="text-sm font-black theme-text-heading uppercase tracking-widest">Precio vigente</h2>
                    <p class="text-[11px] text-slate-400 font-semibold mt-1">Cambiarlo conserva historial.</p>
                </div>
                <form action="{{ route('client.servicios.update-price', $catalogItem) }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Nuevo precio</label>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-xs font-black text-slate-400">$</span>
                            <input type="number" step="0.01" min="0" name="price" value="{{ number_format($catalogItem->current_price, 2, '.', '') }}" required class="w-full text-sm font-black theme-text-heading bg-slate-50 border border-slate-200 rounded-xl py-3 pr-4 pl-8 theme-input focus:ring-4 theme-ring-primary outline-none">
                        </div>
                    </div>
                    <button type="submit" class="w-full theme-button-primary px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em]">
                        Guardar precio
                    </button>
                </form>
            </div>

            <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/70">
                    <h2 class="text-sm font-black theme-text-heading uppercase tracking-widest">Historial de precios</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($catalogItem->priceHistories as $price)
                        <div class="px-6 py-4 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-black theme-text-heading">${{ number_format($price->price, 2) }}</p>
                                <p class="text-[10px] text-slate-400 font-semibold">{{ optional($price->start_date)->format('d/m/Y') ?? 'Sin fecha' }}</p>
                            </div>
                            <span class="text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded-full {{ $price->end_date ? 'bg-slate-100 text-slate-400' : 'theme-bg-primary-soft theme-text-primary' }}">
                                {{ $price->end_date ? 'Anterior' : 'Actual' }}
                            </span>
                        </div>
                    @empty
                        <p class="px-6 py-8 text-xs text-slate-400 font-bold">Sin historial de precios.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            @if($catalogItem->has_inventory && $catalogItem->inventory)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white border border-slate-200 rounded-[20px] p-5 shadow-sm">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Stock actual</p>
                        <p class="text-3xl font-black theme-text-heading mt-2">{{ number_format($catalogItem->inventory->stock_actual, 2) }}</p>
                    </div>
                    <div class="bg-white border border-slate-200 rounded-[20px] p-5 shadow-sm">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Stock minimo</p>
                        <p class="text-3xl font-black theme-text-heading mt-2">{{ number_format($catalogItem->inventory->stock_minimo, 2) }}</p>
                    </div>
                    <div class="bg-white border border-slate-200 rounded-[20px] p-5 shadow-sm">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Venta sin stock</p>
                        <form action="{{ route('client.servicios.toggle-negative-stock', $catalogItem) }}" method="POST" class="mt-3">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                    class="flex items-center gap-2 group focus:outline-none"
                                    title="{{ $catalogItem->inventory->allow_negative_stock ? 'Click para bloquear venta sin stock' : 'Click para permitir venta sin stock' }}">
                                <div class="w-10 h-6 flex items-center p-1 rounded-full transition-colors duration-300 {{ $catalogItem->inventory->allow_negative_stock ? 'bg-rose-500' : 'bg-slate-300' }}">
                                    <div class="w-4 h-4 bg-white rounded-full shadow-sm transition-transform duration-300 transform {{ $catalogItem->inventory->allow_negative_stock ? 'translate-x-4' : 'translate-x-0' }}"></div>
                                </div>
                                <span class="text-[10px] font-bold uppercase tracking-wider {{ $catalogItem->inventory->allow_negative_stock ? 'text-rose-600' : 'text-slate-400' }}">
                                    {{ $catalogItem->inventory->allow_negative_stock ? 'Permitida' : 'Bloqueada' }}
                                </span>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/70">
                        <h2 class="text-sm font-black theme-text-heading uppercase tracking-widest">Registrar movimiento</h2>
                        <p class="text-[11px] text-slate-400 font-semibold mt-1">Entradas y ajustes manuales quedan en Kardex.</p>
                    </div>
                    <form action="{{ route('client.servicios.movements.store', $catalogItem) }}" method="POST" class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                        @csrf
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Tipo</label>
                            <select name="type" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-bold theme-text-heading theme-input outline-none">
                                <option value="purchase">Entrada / reposicion</option>
                                <option value="adjustment_in">Ajuste positivo</option>
                                <option value="adjustment_out">Ajuste negativo</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Cantidad</label>
                            <input type="number" step="0.01" min="0.01" name="quantity" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-bold theme-text-heading theme-input outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Motivo</label>
                            <input type="text" name="reason" maxlength="150" placeholder="Opcional" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-bold theme-text-heading theme-input outline-none">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full theme-button-dark px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em]">
                                Registrar
                            </button>
                        </div>
                        <div class="md:col-span-4">
                            <textarea name="notes" rows="2" maxlength="500" placeholder="Notas opcionales del movimiento..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold theme-text-heading theme-input outline-none resize-none"></textarea>
                        </div>
                    </form>
                </div>

                <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/70">
                        <h2 class="text-sm font-black theme-text-heading uppercase tracking-widest">Kardex de movimientos</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100">
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Fecha</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Movimiento</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Cantidad</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Antes</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Despues</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Motivo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($catalogItem->inventory->movements as $movement)
                                    <tr>
                                        <td class="px-6 py-4 text-xs font-bold text-slate-500">{{ $movement->occurred_at->format('d/m/Y H:i') }}</td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest {{ $movement->direction === 'in' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                                                {{ str_replace('_', ' ', $movement->type) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-xs font-black theme-text-heading">{{ number_format($movement->quantity, 2) }}</td>
                                        <td class="px-6 py-4 text-xs font-bold text-slate-500">{{ number_format($movement->stock_before, 2) }}</td>
                                        <td class="px-6 py-4 text-xs font-black theme-text-heading">{{ number_format($movement->stock_after, 2) }}</td>
                                        <td class="px-6 py-4 text-xs font-semibold text-slate-500">{{ $movement->reason ?? '---' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-10 text-center text-xs font-bold text-slate-400">Sin movimientos registrados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm p-10 text-center">
                    <p class="text-sm font-black theme-text-heading">Este registro no maneja inventario.</p>
                    <p class="text-xs text-slate-400 font-semibold mt-2">Los servicios y productos no inventariables solo muestran precio e historial.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
