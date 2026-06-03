@extends('layouts.client')

@section('title', 'Clientes')

@section('content')
<div class="space-y-8" x-data="{ customerModal: false }">
    
    {{-- INCLUSIÓN DEL SISTEMA DE TOASTS FLOTANTES --}}
    <div class="fixed top-4 right-4 z-[99] space-y-3 min-w-[320px]">
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition class="bg-white border-l-4 border-emerald-500 rounded-xl shadow-xl p-4 flex items-center justify-between border border-slate-100">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold">✓</span>
                    <div>
                        <p class="text-xs font-black text-[#0F172A] uppercase tracking-wider">Operación Exitosa</p>
                        <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ session('success') }}</p>
                    </div>
                </div>
                <button @click="show = false" class="text-slate-400 hover:text-slate-600 text-xs ml-4">✕</button>
            </div>
        @endif

        @if(session('error') || $errors->any())
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition class="bg-white border-l-4 border-red-500 rounded-xl shadow-xl p-4 flex items-center justify-between border border-slate-100">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-sm font-bold">✕</span>
                    <div>
                        <p class="text-xs font-black text-[#0F172A] uppercase tracking-wider">Error de Registro</p>
                        <p class="text-[11px] text-slate-500 font-semibold mt-0.5">
                            {{ session('error') ?? 'Por favor, verifica los campos obligatorios del formulario.' }}
                        </p>
                    </div>
                </div>
                <button @click="show = false" class="text-slate-400 hover:text-slate-600 text-xs ml-4">✕</button>
            </div>
        @endif
    </div>
    
    {{-- HEADER DE LA VISTA --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-[#0F172A] tracking-tighter">Gestión de Customers</h1>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Administra la base de datos de tus clientes y sus mascotas.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
            <form method="GET" action="{{ route('client.customers.index') }}" class="relative w-full sm:w-80">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 text-xs">🔍</span>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar cliente, correo o telefono..." class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-12 py-3.5 text-xs font-semibold text-[#0F172A] placeholder-slate-400 focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none shadow-sm">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                @if(request()->filled('q') || request()->filled('status'))
                    <a href="{{ route('client.customers.index') }}" class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-rose-500 text-xs font-black">x</a>
                @endif
            </form>
            <button @click="customerModal = true" class="inline-flex items-center justify-center gap-2 bg-[#0F172A] text-white px-5 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg shadow-slate-200 transition-all group whitespace-nowrap">
                <span class="text-sm transition-transform group-hover:scale-125">+</span>
                Nuevo Customer
            </button>
        </div>
    </div>

    {{-- CARDS / TRES KPIS SUPERIORES CON TOTALES REALES --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white border border-blue-100 rounded-[24px] p-6 shadow-sm flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:border-blue-300 hover:shadow-md hover:shadow-blue-50/50">
            <div class="space-y-1">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Customers</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-[#0F172A] tracking-tight">{{ $customers->total() }}</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-blue-50/50 text-blue-500 flex items-center justify-center text-xl">👥</div>
        </div>

        <div class="bg-white border border-emerald-100 rounded-[24px] p-6 shadow-sm flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:border-emerald-300 hover:shadow-md hover:shadow-emerald-50/50">
            <div class="space-y-1">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Página Actual</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-[#0F172A] tracking-tight">{{ $customers->count() }}</span>
                    <span class="text-[10px] font-medium text-slate-400">registros aquí</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50/50 text-emerald-500 flex items-center justify-center text-xl">✓</div>
        </div>

        <div class="bg-white border border-teal-100 rounded-[24px] p-6 shadow-sm flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:border-[#38B2AC]/40 hover:shadow-md hover:shadow-teal-50/50">
            <div class="space-y-1">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Último Registro</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-sm font-bold text-[#0F172A] truncate max-w-[140px]">
                        {{ $customers->first()->name ?? 'Ninguno' }}
                    </span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-teal-50/50 text-[#38B2AC] flex items-center justify-center text-xl">🐾</div>
        </div>
    </div>

    {{-- CONTENEDOR DE BASE DE DATOS --}}
    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        
        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Database de Clientes</h3>
            @if(request()->filled('q') || request()->filled('status'))
                <span class="text-[11px] font-bold text-slate-400">Filtros activos</span>
            @endif
        </div>

        {{-- TABLA CON RECORRIDO DINÁMICO --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/20">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Customer Name</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Contact Info</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Primary Pets</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Created At</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($customers as $customer)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            {{-- Nombre Completo usando el Accessor de tu Modelo --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-[#0F172A] text-white flex items-center justify-center font-bold text-xs">
                                        {{ substr($customer->name, 0, 1) }}{{ substr($customer->last_name, 0, 1) }}
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-[#0F172A] leading-tight">{{ $customer->full_name }}</h4>
                                        <p class="text-[10px] font-medium text-slate-400 mt-0.5">ID: #{{ $customer->id }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Información de Contacto --}}
                            <td class="px-6 py-4">
                                <div class="text-xs font-semibold text-[#0F172A]">{{ $customer->email ?? 'Sin Correo' }}</div>
                                <div class="text-[10px] text-slate-400 mt-0.5">{{ $customer->phone ?? 'Sin Teléfono' }}</div>
                            </td>

                            {{-- Mascotas (Relación belongsTo/hasMany dinámico) --}}
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1.5 max-w-[220px]">
                                    @forelse($customer->animals as $animal)
                                        <span class="text-[10px] font-bold text-[#38B2AC] bg-[#38B2AC]/10 px-2 py-0.5 rounded-md">
                                            {{ $animal->name }} ({{ $animal->specie ?? 'Mascota' }})
                                        </span>
                                    @empty
                                        <span class="text-[10px] text-slate-400 font-medium italic">Sin mascotas</span>
                                    @endforelse
                                </div>
                            </td>

                            {{-- Fecha de Registro --}}
                            <td class="px-6 py-4">
                                <div class="text-xs font-bold text-[#0F172A]">{{ $customer->created_at->format('M d, Y') }}</div>
                                <div class="text-[9px] font-medium text-slate-400 mt-0.5">{{ $customer->created_at->diffForHumans() }}</div>
                            </td>

                            {{-- Status Badge Dinámico --}}
                            <td class="px-6 py-4">
                                @if($customer->status === 'active')
                                    <span class="inline-flex items-center text-[9px] font-black uppercase tracking-widest text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">Active</span>
                                @else
                                    <span class="inline-flex items-center text-[9px] font-black uppercase tracking-widest text-red-700 bg-red-50 px-2.5 py-1 rounded-full">Inactive</span>
                                @endif
                            </td>

                            {{-- Acciones --}}
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('client.customers.show', $customer->id) }}" 
                                        class="p-1.5 text-slate-400 hover:text-[#38B2AC] transition-colors" 
                                        title="Ver ficha">👁</a>
                                    <!-- <button class="p-1.5 text-slate-400 hover:text-[#0F172A] transition-colors" title="Editar">✏️</button> -->
                                </div>
                            </td>
                        </tr>
                    @empty
                        {{-- Estado vacío si la consulta no devuelve nada --}}
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <p class="text-sm font-bold text-slate-400">No se encontraron clientes registrados en este Tenant.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINACIÓN NATIVA DE LARAVEL CON ESTILOS TAILWIND --}}
        <div class="p-6 border-t border-slate-100 bg-slate-50/30 dynamic-pagination">
            {{ $customers->links() }}
        </div>
    </div>

    {{-- MODAL NUEVO CUSTOMER --}}
    <div x-show="customerModal" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-data="{ loading: false }">
        <div class="flex items-center justify-center min-h-screen px-4 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-[#0F172A]/80 backdrop-blur-sm" @click="if(!loading) customerModal = false"></div>
            <div class="inline-block overflow-hidden text-left align-middle transition-all transform bg-white rounded-[24px] shadow-2xl sm:my-8 sm:max-w-2xl sm:w-full border border-slate-100 relative" x-show="customerModal" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0">
                
                {{-- SPINNER DE CARGA --}}
                <div x-show="loading" class="absolute inset-0 bg-white/80 backdrop-blur-md z-50 flex flex-col items-center justify-center gap-4" style="display: none;">
                    <div class="w-10 h-10 border-4 border-slate-200 border-t-[#38B2AC] rounded-full animate-spin"></div>
                    <p class="text-[10px] font-black text-[#0F172A] uppercase tracking-[0.2em] animate-pulse">Guardando en Base de Datos...</p>
                </div>

                <form action="{{ route('client.customers.store') }}" method="POST" @submit="loading = true">
                    @csrf
                    <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h3 class="text-lg font-black text-[#0F172A] tracking-tighter">Registrar Nuevo Cliente (Owner)</h3>
                        <button type="button" @click="customerModal = false" :disabled="loading" class="text-slate-400 hover:text-red-500 transition-colors">✕</button>
                    </div>
                    <div class="p-8 space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Nombre *</label>
                                <input type="text" name="name" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Apellidos *</label>
                                <input type="text" name="last_name" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Correo Electrónico *</label>
                            <input type="email" name="email" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Teléfono Principal *</label>
                                <input type="text" name="phone" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Teléfono Secundario</label>
                                <input type="text" name="secondary_phone" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Dirección Residencial</label>
                            <input type="text" name="address" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Notas / Observaciones</label>
                            <textarea name="notes" rows="3" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none resize-none"></textarea>
                        </div>
                    </div>
                    <div class="px-8 py-6 bg-slate-50 flex items-center justify-end gap-3 border-t border-slate-100">
                        <button type="button" @click="customerModal = false" :disabled="loading" class="text-xs font-black uppercase tracking-widest text-slate-400 hover:text-slate-600">Cancelar</button>
                        <button type="submit" :disabled="loading" class="bg-[#0F172A] px-6 py-3.5 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg">Guardar Registro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
