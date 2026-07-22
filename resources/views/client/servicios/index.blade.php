@extends('layouts.client')

@section('title', 'Configuración General')

@section('contextual-tour', 'services')

@section('content')
@php
    $showKpiCards = \App\Support\TenantKpiVisibility::isVisible(auth()->user()?->tenant, \App\Support\TenantKpiVisibility::SERVICIOS_INDEX);
@endphp
<div x-data="{ openForm: false, type: 'service', hasInventory: false }" class="p-6 max-w-7xl mx-auto space-y-6">

{{-- ENCABEZADO PRINCIPAL DEL MÓDULO --}}
<div data-tour="services-header" class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-xl font-black theme-text-heading uppercase tracking-widest">Catálogo de Servicios y Productos</h1>
        <p class="text-xs text-slate-400 font-medium mt-0.5">Administra los servicios clínicos, estéticos y productos comerciales de tu veterinaria.</p>
    </div>
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full md:w-auto">

        <a href="{{ route('client.servicios.inventory') }}" class="theme-bg-primary-soft theme-text-primary-strong border theme-border-primary-soft theme-hover-border-primary-soft px-5 py-3 rounded-xl font-bold text-xs tracking-wide shadow-sm transition-all flex items-center justify-center gap-2 whitespace-nowrap">
            Inventario
        </a>

        <button data-tour="add-service" @click="openForm = !openForm" class="theme-button-dark px-5 py-3 rounded-xl font-bold text-xs tracking-wide shadow-sm transition-all flex items-center justify-center gap-2 whitespace-nowrap">
            <span x-text="openForm ? 'Cancelar Registro' : '+ Agregar al Catalogo'"></span>
        </button>
    </div>
</div>

@if($showKpiCards)
{{-- CARDS / TRES KPIS SUPERIORES --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">

    {{-- KPI 1: PRODUCTO ESTRELLA --}}
    <div class="group theme-surface-dark border border-slate-900 rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full theme-bg-primary-soft"></div>
        <div class="absolute right-8 bottom-8 w-16 h-16 rounded-full bg-white/10"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
            <div class="space-y-1">
                <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Producto Estrella</p>
                <div class="flex items-baseline gap-2">
                    @if($starProduct)
                        <span class="text-sm font-bold text-white truncate max-w-[140px]">{{ $starProduct->catalogItem->name }}</span>
                    @else
                        <span class="text-sm font-bold text-white">N/A</span>
                    @endif
                </div>
                @if($starProduct)
                    <p class="text-[10px] font-semibold text-slate-300">Vendido {{ $starProduct->total_quantity_sold }} veces este mes</p>
                @else
                    <p class="text-[10px] font-semibold text-slate-300">Sin ventas este mes</p>
                @endif
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/10 text-white flex items-center justify-center text-xl group-hover:scale-110 transition-transform">⭐</div>
        </div>
    </div>

    {{-- KPI 2: PRODUCTOS CON INVENTARIO --}}
    <div class="group theme-gradient-primary theme-border-primary rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl relative overflow-hidden">
        <div class="absolute -right-8 -bottom-8 w-32 h-32 rounded-full bg-white/20"></div>
        <div class="absolute -left-4 -top-4 w-20 h-20 rounded-full bg-white/10"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
            <div class="space-y-1">
                <p class="text-[10px] font-black text-white/80 uppercase tracking-widest">Productos con Inventario</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-white tracking-tight">{{ $inventoryProductsCount }}</span>
                </div>
                <p class="text-[10px] font-medium text-white/80">productos inventariables</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/20 text-white flex items-center justify-center text-xl group-hover:scale-110 transition-transform">📦</div>
        </div>
    </div>

    {{-- KPI 3: ÚLTIMO MOVIMIENTO / VENTA --}}
    <div class="group theme-bg-primary-soft border theme-border-primary-soft rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full bg-white/15"></div>
        <div class="absolute left-8 bottom-8 w-16 h-16 rounded-full bg-white/10"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
            <div class="space-y-1">
                <p class="text-[10px] font-black theme-text-primary-strong uppercase tracking-widest">Último Movimiento</p>
                <div class="flex items-baseline gap-2">
                    @if($lastCatalogItemMovement)
                        <span class="text-sm font-bold theme-text-heading truncate max-w-[140px]">{{ $lastCatalogItemMovement->name }}</span>
                    @else
                        <span class="text-sm font-bold theme-text-heading">N/A</span>
                    @endif
                </div>
                @if($lastCatalogItemMovement)
                    <p class="text-[10px] font-semibold text-slate-400">Tipo: {{ $lastCatalogItemMovement->type }}</p>
                @else
                    <p class="text-[10px] font-semibold text-slate-400">Sin movimientos</p>
                @endif
            </div>
            <div class="w-12 h-12 rounded-2xl theme-bg-primary text-white flex items-center justify-center text-xl group-hover:scale-110 transition-transform">🔄</div>
        </div>
    </div>
</div>
@endif

    {{-- FORMULARIO DE ALTA --}}
    <div x-show="openForm" x-collapse class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50">
            <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Nuevo Artículo</h3>
            <p class="text-[11px] text-slate-400 font-medium mt-0.5">Define si es un servicio o producto. El historial de precios se congelará automáticamente al guardar.</p>
        </div>

        <form action="{{ route('client.servicios.store') }}" method="POST" class="p-6 space-y-6">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Tipo de Artículo --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Tipo de Registro</label>
                    <select name="type" x-model="type" @change="if(type === 'service') { hasInventory = false; }" class="w-full text-xs font-semibold theme-text-heading bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:outline-none theme-input transition-colors">
                        <option value="service">⚙️ Servicio (Consulta, Estética, Cirugía)</option>
                        <option value="product">📦 Producto (Medicamento, Accesorio, Alimento)</option>
                    </select>
                </div>

                {{-- Nombre Comercial --}}
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Nombre del Servicio o Producto</label>
                    <input type="text" name="name" required placeholder="Ej: Consulta Médica General o Alimento Nupec Adulto 2Kg" value="{{ old('name') }}" class="w-full text-xs font-semibold theme-text-heading bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:outline-none theme-input transition-colors">
                    @error('name') <span class="text-[11px] text-red-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- SKU / Código --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">SKU / Código</label>
                    <input type="text" name="sku" 
                           :placeholder="type === 'service' ? 'Automático (SERV-00X)' : 'Automático (PROD-00X)'" 
                           value="{{ old('sku') }}" 
                           class="w-full text-xs font-mono theme-text-heading bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:outline-none theme-input transition-colors">
                    <p class="text-[9px] text-slate-400 font-semibold mt-1">Si se deja vacío, se generará un folio autoincremental.</p>
                    @error('sku') <span class="text-[11px] text-red-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Precio Inicial --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Precio de Venta ($ NETO)</label>
                    <input type="number" step="0.01" name="price" required placeholder="0.00" value="{{ old('price') }}" class="w-full text-xs font-bold theme-text-heading bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:outline-none theme-input transition-colors">
                    @error('price') <span class="text-[11px] text-red-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Toggle de Inventario Opcional (Solo visible si es Producto) --}}
                <div x-show="type === 'product'" x-transition class="flex items-center h-full pt-4">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="has_inventory" value="1" x-model="hasInventory" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all theme-peer-checked-bg-primary"></div>
                        <span class="ml-3 text-xs font-black uppercase tracking-widest text-slate-500">¿Controla Inventario?</span>
                    </label>
                </div>
            </div>

            {{-- Bloque de Existencias (Se despliega dinámicamente si hasInventory es true) --}}
            <div x-show="hasInventory" x-collapse class="grid grid-cols-1 md:grid-cols-3 gap-6 p-4 bg-slate-50 border border-slate-100 rounded-2xl">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Stock Inicial Actual</label>
                    <input type="number" step="0.01" name="stock_actual" placeholder="0.00" :required="hasInventory" value="{{ old('stock_actual', '0') }}" class="w-full text-xs font-semibold theme-text-heading bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:outline-none theme-input transition-colors">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Stock Mínimo (Alerta de escasez)</label>
                    <input type="number" step="0.01" name="stock_minimo" placeholder="0.00" :required="hasInventory" value="{{ old('stock_minimo', '0') }}" class="w-full text-xs font-semibold theme-text-heading bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:outline-none theme-input transition-colors">
                </div>
                <div class="flex items-center">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="allow_negative_stock" value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-500"></div>
                        <span class="ml-3 text-xs font-black uppercase tracking-widest text-slate-500">Permitir venta sin existencias</span>
                    </label>
                </div>
            </div>

            {{-- Descripción --}}
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Descripción o detalles internos</label>
                <textarea name="description" rows="2" placeholder="Detalles adicionales opcionales..." class="w-full text-xs font-semibold theme-text-heading bg-white border border-slate-200 rounded-xl p-4 focus:outline-none theme-input transition-colors">{{ old('description') }}</textarea>
            </div>

            {{-- Botón de envío --}}
            <div class="flex justify-end pt-2">
                <button type="submit" class="theme-button-primary px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-sm">
                    Guardar en Catálogo
                </button>
            </div>
        </form>
    </div>

    {{-- LISTADO EN TABLA --}}
    <div data-tour="services-list" class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50 space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Catálogo registrado</h3>
                <form method="GET" action="{{ route('client.servicios.index') }}" class="flex items-center gap-2">
                    @if($search !== '')
                        <input type="hidden" name="q" value="{{ $search }}">
                    @endif
                    <label for="services-per-page" class="text-[10px] font-black uppercase tracking-widest text-slate-400">Mostrar</label>
                    <select id="services-per-page" name="per_page" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold theme-text-heading outline-none theme-input">
                        @foreach([15, 30, 50, 100] as $option)
                            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <span class="text-[10px] font-bold text-slate-400">filas</span>
                </form>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" action="{{ route('client.servicios.index') }}" class="relative w-full sm:max-w-md">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 text-xs">🔍</span>
                    <input type="text" name="q" value="{{ $search }}" placeholder="Buscar servicio, producto o SKU..." class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-12 py-3 text-xs font-semibold theme-text-heading placeholder-slate-400 theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-sm">
                    @if($search !== '')
                        <a href="{{ route('client.servicios.index', ['per_page' => $perPage]) }}" class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-rose-500 text-xs font-black">x</a>
                    @endif
                </form>
                @if($search !== '')
                    <span class="text-[11px] font-bold text-slate-400">Filtro: {{ $search }}</span>
                @endif
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/10">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Artículo / Concepto</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">SKU</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Tipo</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Precio Vigente</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Existencias</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Venta sin Stock</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($items as $item)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            {{-- Nombre e Icono --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg {{ $item->type === 'service' ? 'theme-bg-primary-soft theme-text-primary' : 'bg-amber-50 text-amber-600' }} flex items-center justify-center font-black text-xs">
                                        {{ $item->type === 'service' ? '⚙️' : '📦' }}
                                    </div>
                                    <div>
                                        <span class="text-xs font-bold theme-text-heading block">{{ $item->name }}</span>
                                        @if($item->description)
                                            <span class="text-[10px] text-slate-400 font-medium block max-w-xs truncate">{{ $item->description }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- SKU --}}
                            <td class="px-6 py-4 text-xs font-mono text-slate-400">
                                {{ $item->sku ?? '---' }}
                            </td>

                            {{-- Tipo Badge --}}
                            <td class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">
                                {{ $item->type === 'service' ? 'Servicio' : 'Producto' }}
                            </td>

                            {{-- Precio Actual --}}
                            <td class="px-6 py-4 text-xs font-black theme-text-heading">
                                ${{ number_format($item->current_price, 2) }}
                            </td>

                            {{-- Existencias --}}
                            <td class="px-6 py-4 text-xs font-semibold">
                                @if($item->has_inventory && $item->inventory)
                                    <span class="{{ $item->inventory->stock_actual <= $item->inventory->stock_minimo ? 'text-rose-600 font-bold' : 'text-slate-600' }}">
                                        {{ number_format($item->inventory->stock_actual, 2) }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-medium block">Min: {{ number_format($item->inventory->stock_minimo, 0) }}</span>
                                @else
                                    <span class="text-slate-300 italic text-[11px]">No inventariable</span>
                                @endif
                            </td>

                            {{-- Venta sin Stock (Toggle) --}}
                            <td class="px-6 py-4">
                                @if($item->has_inventory && $item->inventory)
                                    <form action="{{ route('client.servicios.toggle-negative-stock', $item) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" 
                                                class="flex items-center gap-2 group focus:outline-none"
                                                title="{{ $item->inventory->allow_negative_stock ? 'Click para Bloquear' : 'Click para Permitir' }}">
                                            
                                            <div class="w-10 h-6 flex items-center p-1 rounded-full transition-colors duration-300 {{ $item->inventory->allow_negative_stock ? 'bg-rose-500' : 'bg-slate-300' }}">
                                                <div class="w-4 h-4 bg-white rounded-full shadow-sm transition-transform duration-300 transform {{ $item->inventory->allow_negative_stock ? 'translate-x-4' : 'translate-x-0' }}"></div>
                                            </div>
                                            
                                            <span class="text-[10px] font-bold uppercase tracking-wider {{ $item->inventory->allow_negative_stock ? 'text-rose-600' : 'text-slate-400' }}">
                                                {{ $item->inventory->allow_negative_stock ? 'Permitida' : 'Bloqueada' }}
                                            </span>
                                        </button>
                                    </form>
                                @else
                                    <span class="text-slate-300 italic text-[11px]">---</span>
                                @endif
                            </td>

                            {{-- Estado (Toggle) --}}
                         <td class="px-6 py-4">
    <form action="{{ route('client.servicios.toggle', $item) }}" method="POST">
        @csrf
        @method('PATCH')
        <button type="submit" 
                class="flex items-center gap-2 group focus:outline-none"
                title="{{ $item->is_active ? 'Click para Inhabilitar' : 'Click para Habilitar' }}">
            
            <div class="w-10 h-6 flex items-center p-1 rounded-full transition-colors duration-300 {{ $item->is_active ? 'theme-bg-primary' : 'bg-slate-300' }}">
                <div class="w-4 h-4 bg-white rounded-full shadow-sm transition-transform duration-300 transform {{ $item->is_active ? 'translate-x-4' : 'translate-x-0' }}"></div>
            </div>
            
            <span class="text-[10px] font-bold uppercase tracking-wider {{ $item->is_active ? 'theme-text-primary-strong' : 'text-slate-400' }}">
                {{ $item->is_active ? 'Activo' : 'Inactivo' }}
            </span>
        </button>
    </form>
</td>

                            {{-- Acciones --}}
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('client.servicios.show', $item) }}"
                                       class="p-1.5 text-slate-400 theme-hover-text-primary transition-colors text-xl leading-none"
                                       title="Ver detalle">🔍</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-sm font-bold text-slate-400">
                                Tu catálogo está vacío. Haz clic en "+ Agregar al Catálogo" para inicializar tus servicios.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-6 border-t border-slate-100 bg-slate-50/30">
            {{ $items->links() }}
        </div>
    </div>

</div>
@endsection
