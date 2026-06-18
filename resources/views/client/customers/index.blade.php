@extends('layouts.client')

@section('title', 'Clientes')

@section('contextual-tour', 'customers')

@section('content')
<div class="space-y-8" x-data="{ customerModal: false }">
    
    {{-- INCLUSIÓN DEL SISTEMA DE TOASTS FLOTANTES --}}
    <div class="fixed top-4 right-4 z-[99] space-y-3 min-w-[320px]">
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition class="bg-white border-l-4 border-emerald-500 rounded-xl shadow-xl p-4 flex items-center justify-between border border-slate-100">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold">✓</span>
                    <div>
                        <p class="text-xs font-black theme-text-heading uppercase tracking-wider">Operación Exitosa</p>
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
                        <p class="text-xs font-black theme-text-heading uppercase tracking-wider">Error de Registro</p>
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
    <div data-tour="customers-header" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black theme-text-heading tracking-tighter">Gestión de Clientes</h1>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Administra la base de datos de tus clientes y sus mascotas.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
            <form method="GET" action="{{ route('client.customers.index') }}" class="relative w-full sm:w-80">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 text-xs">🔍</span>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar cliente, correo o telefono..." class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-12 py-3.5 text-xs font-semibold theme-text-heading placeholder-slate-400 theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-sm">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                @if(request()->filled('q') || request()->filled('status'))
                    <a href="{{ route('client.customers.index') }}" class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-rose-500 text-xs font-black">x</a>
                @endif
            </form>
            <button data-tour="add-customer" @click="customerModal = true" class="inline-flex items-center justify-center gap-2 theme-surface-dark px-5 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg shadow-slate-200 transition-all group whitespace-nowrap">
                <span class="text-sm transition-transform group-hover:scale-125">+</span>
                Nuevo Cliente
            </button>
        </div>
    </div>

    @include('client.customers.partials.activation-invite')

    {{-- CARDS / TRES KPIS SUPERIORES CON COLORES Y DEGRADADOS --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    
    {{-- CARD 1: CUSTOMERS --}}
    <div class="group theme-surface-dark border border-slate-900 rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl">
        <div class="space-y-1">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Clientes</p>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-white tracking-tight">{{ $customers->total() }}</span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-white/10 text-white flex items-center justify-center text-xl group-hover:scale-110 transition-transform">👥</div>
    </div>

    {{-- CARD 2: PÁGINA ACTUAL --}}
    <div class="group theme-gradient-primary theme-border-primary rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl">
        <div class="space-y-1">
            <p class="text-[10px] font-black text-white/80 uppercase tracking-widest">Página Actual</p>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-white tracking-tight">{{ $customers->count() }}</span>
                <span class="text-[10px] font-medium text-white/80">registros aquí</span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-2xl theme-bg-primary text-white flex items-center justify-center text-xl group-hover:scale-110 transition-transform">✓</div>
    </div>

    {{-- CARD 3: ÚLTIMO REGISTRO --}}
    <div class="group theme-bg-primary-soft border theme-border-primary-soft rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl">
        <div class="space-y-1">
            <p class="text-[10px] font-black theme-text-primary-strong uppercase tracking-widest">Último Registro</p>
            <div class="flex items-baseline gap-2">
                <span class="text-sm font-bold theme-text-heading truncate max-w-[140px]">
                    {{ $customers->first()->name ?? 'Ninguno' }}
                </span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-2xl theme-bg-primary text-white flex items-center justify-center text-xl group-hover:scale-110 transition-transform">🐾</div>
    </div>
</div>

    {{-- CONTENEDOR DE BASE DE DATOS --}}
    <div data-tour="customers-list" class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        
        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Listado de Clientes</h3>
            @if(request()->filled('q') || request()->filled('status'))
                <span class="text-[11px] font-bold text-slate-400">Filtros activos</span>
            @endif
        </div>

        {{-- TABLA CON RECORRIDO DINÁMICO --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/20 text-center">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Cliente</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Contacto</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Pacientes</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Adeudo General</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">APP</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Detalles</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($customers as $customer)
                        <tr class="hover:bg-slate-50/60 transition-colors text-center">
                            {{-- Nombre Completo usando el Accessor de tu Modelo --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full theme-surface-dark flex items-center justify-center font-bold text-xs">
                                        {{ substr($customer->name, 0, 1) }}{{ substr($customer->last_name, 0, 1) }}
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold theme-text-heading leading-tight">{{ $customer->full_name }}</h4>
                                        <p class="text-[10px] font-medium text-slate-400 mt-0.5">ID: #{{ $customer->id }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Información de Contacto --}}
                            <td class="px-6 py-4">
                                <div class="text-xs font-semibold theme-text-heading">{{ $customer->email ?? 'Sin Correo' }}</div>
                                <div class="text-[10px] text-slate-400 mt-0.5">{{ $customer->phone ?? 'Sin Teléfono' }}</div>
                            </td>

                            {{-- Mascotas (Relación belongsTo/hasMany dinámico) --}}
                            {{-- Cantidad de Mascotas --}}
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center text-[10px] font-black theme-text-primary theme-bg-primary-soft px-2.5 py-1 rounded-full">
                                    {{ $customer->animals_count ?? $customer->animals->count() }}
                                    {{ ($customer->animals_count ?? $customer->animals->count()) == 1 ? 'paciente' : 'pacientes ' }}
                                </span>
                            </td>
                            {{-- <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1.5 max-w-[220px]">
                                    @forelse($customer->animals as $animal)
                                        <span class="text-[10px] font-bold theme-text-primary theme-bg-primary-soft px-2 py-0.5 rounded-md">
                                            {{ $animal->name }} ({{ $animal->specie ?? 'Mascota' }})
                                        </span>
                                    @empty
                                        <span class="text-[10px] text-slate-400 font-medium italic">Sin mascotas</span>
                                    @endforelse
                                </div>
                            </td> --}}

                            {{-- Adeudo General --}}
                            <td class="px-6 py-4">
                                @php($generalDebt = (float) ($customer->general_debt ?? 0))
                                <div class="text-xs font-black {{ $generalDebt > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                    ${{ number_format($generalDebt, 2) }}
                                </div>
                                <div class="text-[9px] font-medium {{ $generalDebt > 0 ? 'text-rose-400' : 'text-emerald-500' }} mt-0.5">
                                    {{ $generalDebt > 0 ? 'Pendiente' : 'Sin adeudo' }}
                                </div>
                            </td>

                            {{-- Status Toggle Dinámico --}}
                            <td class="px-6 py-4">
                                @php($portalAccessActive = $customer->portalAccesses->firstWhere('status', 'active'))
                                <form action="{{ route('client.customers.portal-access.toggle', $customer) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="inline-flex items-center gap-2 group focus:outline-none"
                                            title="{{ $portalAccessActive ? 'Suspender acceso app/web' : 'Activar acceso app/web' }}">
                                        <div class="w-10 h-6 flex items-center p-1 rounded-full transition-colors duration-300 {{ $portalAccessActive ? 'bg-indigo-500' : 'bg-slate-300' }}">
                                            <div class="w-4 h-4 bg-white rounded-full shadow-sm transition-transform duration-300 transform {{ $portalAccessActive ? 'translate-x-4' : 'translate-x-0' }}"></div>
                                        </div>

                                        <span class="text-[10px] font-bold uppercase tracking-wider min-w-[34px] {{ $portalAccessActive ? 'text-indigo-600' : 'text-slate-400' }}">
                                            {{ $portalAccessActive ? 'ON' : 'OFF' }}
                                        </span>
                                    </button>
                                </form>
                            </td>

                  <td class="px-6 py-4">
                    <form action="{{ route('client.customers.toggle', $customer->id) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit" 
                                class="flex items-center gap-2 group focus:outline-none"
                                title="{{ $customer->status === 'active' ? 'Click para Inactivar' : 'Click para Activar' }}">
                            
                            <div class="w-10 h-6 flex items-center p-1 rounded-full transition-colors duration-300 {{ $customer->status === 'active' ? 'theme-bg-primary' : 'bg-slate-300' }}">
                                <div class="w-4 h-4 bg-white rounded-full shadow-sm transition-transform duration-300 transform {{ $customer->status === 'active' ? 'translate-x-4' : 'translate-x-0' }}"></div>
                            </div>
                            
                            <span class="text-[10px] font-bold uppercase tracking-wider min-w-[50px] {{ $customer->status === 'active' ? 'theme-text-primary-strong' : 'text-slate-400' }}">
                                {{ $customer->status === 'active' ? 'Active' : 'Inactive' }}
                            </span>
                        </button>
                    </form>
                </td>

                            {{-- Acciones --}}
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('client.customers.show', $customer->id) }}" 
                                        class="p-1.5 text-slate-400 theme-hover-text-primary transition-colors"
                                        title="Ver ficha">🔍</a>
                                    <!-- <button class="p-1.5 text-slate-400 theme-hover-text-heading transition-colors" title="Editar">✏️</button> -->
                                </div>
                            </td>
                        </tr>
                    @empty
                        {{-- Estado vacío si la consulta no devuelve nada --}}
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
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
            <div class="fixed inset-0 transition-opacity theme-overlay backdrop-blur-sm" @click="if(!loading) customerModal = false"></div>
            <div class="inline-block overflow-hidden text-left align-middle transition-all transform bg-white rounded-[24px] shadow-2xl sm:my-8 sm:max-w-2xl sm:w-full border border-slate-100 relative" x-show="customerModal" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0">
                
                {{-- SPINNER DE CARGA --}}
                <div x-show="loading" class="absolute inset-0 bg-white/80 backdrop-blur-md z-50 flex flex-col items-center justify-center gap-4" style="display: none;">
                    <div class="w-10 h-10 border-4 border-slate-200 theme-spinner-primary rounded-full animate-spin"></div>
                    <p class="text-[10px] font-black theme-text-heading uppercase tracking-[0.2em] animate-pulse">Guardando en Base de Datos...</p>
                </div>

                <form action="{{ route('client.customers.store') }}" method="POST" @submit="loading = true">
                    @csrf
                    <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h3 class="text-lg font-black theme-text-heading tracking-tighter">Registrar Nuevo Cliente (Owner)</h3>
                        <button type="button" @click="customerModal = false" :disabled="loading" class="text-slate-400 hover:text-red-500 transition-colors">✕</button>
                    </div>
                    <div class="p-8 space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Nombre *</label>
                                <input type="text" name="name" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Apellidos *</label>
                                <input type="text" name="last_name" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Correo Electrónico *</label>
                            <input type="email" name="email" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Teléfono Principal *</label>
                                <input type="text" name="phone" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Teléfono Secundario</label>
                                <input type="text" name="secondary_phone" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Dirección Residencial</label>
                            <input type="text" name="address" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Notas / Observaciones</label>
                            <textarea name="notes" rows="3" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading theme-input focus:ring-4 theme-ring-primary transition-all outline-none resize-none"></textarea>
                        </div>
                    </div>
                    <div class="px-8 py-6 bg-slate-50 flex items-center justify-end gap-3 border-t border-slate-100">
                        <button type="button" @click="customerModal = false" :disabled="loading" class="text-xs font-black uppercase tracking-widest text-slate-400 hover:text-slate-600">Cancelar</button>
                        <button type="submit" :disabled="loading" class="theme-surface-dark px-6 py-3.5 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg">Guardar Registro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
