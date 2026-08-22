@extends('layouts.client')

@section('title', 'Pacientes')

@section('contextual-tour', 'animals')

@section('content')
@php
    $showKpiCards = \App\Support\TenantKpiVisibility::isVisible(auth()->user()?->tenant, \App\Support\TenantKpiVisibility::ANIMALS_INDEX);
@endphp
<div class="-mt-4 space-y-6" x-data="{ animalModal: false }">
    
    {{-- SISTEMA DE TOASTS FLOTANTES --}}
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
    
    @if($showKpiCards)
    {{-- CARDS / TRES KPIS SUPERIORES CON HOVER Y OUTLINES PASTEL --}}
    {{-- CARDS / TRES KPIS SUPERIORES CON DEGRADADOS DINÁMICOS --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    
    {{-- KPI 1: TOTAL PACIENTES (VIOLETA OSCURO) --}}
    <div class="group theme-surface-dark border border-slate-900 rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full theme-bg-primary-soft"></div>
        <div class="absolute right-8 bottom-8 w-16 h-16 rounded-full bg-white/10"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
            <div class="space-y-1">
                <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Total Pacientes</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-white tracking-tight">{{ $totalAnimals }}</span>
                </div>
                <p class="text-[10px] font-semibold text-slate-300">{{ $inactiveAnimals }} inactivos</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/10 text-white flex items-center justify-center text-xl group-hover:scale-110 transition-transform">🐕</div>
        </div>
    </div>

    {{-- KPI 2: REGISTROS (AMBAR / NARANJA) --}}
    <div class="group theme-gradient-primary theme-border-primary rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl relative overflow-hidden">
        <div class="absolute -right-8 -bottom-8 w-32 h-32 rounded-full bg-white/20"></div>
        <div class="absolute -left-4 -top-4 w-20 h-20 rounded-full bg-white/10"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
            <div class="space-y-1">
                <p class="text-[10px] font-black text-white/80 uppercase tracking-widest">Registros esta Página</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-white tracking-tight">{{ $animals->count() }}</span>
                    <span class="text-[10px] font-medium text-white/80">pacientes</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/20 text-white flex items-center justify-center text-xl group-hover:scale-110 transition-transform">⚡</div>
        </div>
    </div>

    {{-- KPI 3: ÚLTIMO PACIENTE (TURQUESA DE MARCA) --}}
    <div class="group theme-bg-primary-soft border theme-border-primary-soft rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full bg-white/15"></div>
        <div class="absolute left-8 bottom-8 w-16 h-16 rounded-full bg-white/10"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
            <div class="space-y-1">
                <p class="text-[10px] font-black theme-text-primary-strong uppercase tracking-widest">Último Paciente</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-sm font-bold theme-text-heading truncate max-w-[140px]">
                        {{ $animals->first()->name ?? 'Ninguno' }}
                    </span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/20 text-white flex items-center justify-center text-xl group-hover:scale-110 transition-transform">🐾</div>
        </div>
    </div>
</div>
    @endif

    {{-- CONTENEDOR DE BASE DE DATOS --}}
    <div data-tour="animals-list" class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        
        <div class="p-5 border-b border-slate-100 bg-slate-50/50 space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-black theme-text-heading tracking-tighter">Gestión de Caballos</h1>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Administra los caballos de tu clínica y sus historiales.</p>
                </div>
                <div class="flex flex-col items-start gap-3 sm:items-end">
                    <form method="GET" action="{{ route('client.animals.index') }}" class="flex items-center gap-2">
                        @if(request()->filled('q'))
                            <input type="hidden" name="q" value="{{ request('q') }}">
                        @endif
                        <label for="animals-per-page" class="text-[10px] font-black uppercase tracking-widest text-slate-400">Mostrar</label>
                        <select id="animals-per-page" name="per_page" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold theme-text-heading outline-none theme-input">
                            @foreach([15, 30, 50, 100] as $option)
                                <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        <span class="text-[10px] font-bold text-slate-400">filas</span>
                    </form>

                    <button data-tour="add-animal" @click="animalModal = true" class="inline-flex items-center justify-center gap-2 theme-surface-dark px-5 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] shadow-lg theme-shadow-primary hover:shadow-xl hover:-translate-y-0.5 theme-hover-bg-primary transition-all duration-300 group whitespace-nowrap">
                        <span class="flex items-center justify-center w-4 h-4 rounded-full theme-bg-primary text-white text-xs font-black transition-transform group-hover:scale-125 group-hover:rotate-90 duration-300">+</span>
                        Nuevo Caballo
                    </button>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" action="{{ route('client.animals.index') }}" class="relative w-full sm:max-w-md">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 text-xs">🔍</span>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar caballo, especie o dueño..." class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-12 py-3.5 text-xs font-semibold theme-text-heading placeholder-slate-400 theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-sm">
                    @if(request()->filled('q'))
                        <a href="{{ route('client.animals.index', ['per_page' => $perPage]) }}" class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-rose-500 text-xs font-black">x</a>
                    @endif
                </form>
                @if(request()->filled('q'))
                    <span class="text-[11px] font-bold text-slate-400">Filtro: {{ request('q') }}</span>
                @endif
            </div>
        </div>

        {{-- TABLA DE MASCOTAS --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="theme-surface-dark">
                        <th class="px-4 py-3 pb-4 text-[10px] font-black text-white uppercase tracking-widest">Nombre</th>
                        <th class="px-4 py-3 pb-4 text-[10px] font-black text-white uppercase tracking-widest text-center">Historial</th>
                        <th class="px-4 py-3 pb-4 text-[10px] font-black text-white uppercase tracking-widest text-center">Vacunacion</th>
                        <th class="px-4 py-3 pb-4 text-[10px] font-black text-white uppercase tracking-widest">Cliente</th>
                        <th class="px-4 py-3 pb-4 text-[10px] font-black text-white uppercase tracking-widest">Club</th>
                        <th class="px-4 py-3 pb-4 text-[10px] font-black text-white uppercase tracking-widest">Peso</th>
                        <th class="px-4 py-3 pb-4 text-[10px] font-black text-white uppercase tracking-widest">Alergias</th>
                        <th class="px-4 py-3 pb-4 text-[10px] font-black text-white uppercase tracking-widest">Estatus</th>
                        <th class="px-4 py-3 pb-4 text-[10px] font-black text-white uppercase tracking-widest text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($animals as $animal)
                        <tr class="bg-white hover:bg-slate-50/70 transition-colors">
                            {{-- Info Básica del Animal --}}
                            <td class="px-4 py-4 border-l-4 theme-border-primary">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('client.animals.edit', ['animal' => $animal, 'tab' => 'historial']) }}" class="w-11 h-11 rounded-2xl theme-bg-primary-soft theme-text-primary flex items-center justify-center font-black text-sm shadow-sm ring-1 ring-white transition-all hover:scale-105 hover:opacity-90" title="Ver ficha de {{ $animal->name }}">
                                        {{ substr($animal->name, 0, 1) }}
                                    </a>
                                    <div class="min-w-0">
                                        <a href="{{ route('client.animals.edit', ['animal' => $animal, 'tab' => 'historial']) }}" class="block text-sm font-black theme-text-heading leading-tight theme-hover-text-primary transition-colors hover:underline decoration-2">{{ $animal->name }}</a>
                                        <span class="mt-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-widest text-slate-500">
                                            Caballo
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-4 text-center">
                                <a href="{{ route('client.animals.edit', ['animal' => $animal, 'tab' => 'historial']) }}"
                                   title="Ver historial de servicios"
                                   aria-label="Ver historial de servicios de {{ $animal->name }}"
                                   class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-base text-slate-500 shadow-sm transition-all hover:border-teal-200 hover:bg-teal-50 hover:text-teal-700">
                                    &#128203;
                                </a>
                            </td>

                            <td class="px-4 py-4 text-center">
                                <a href="{{ route('client.animals.edit', ['animal' => $animal, 'tab' => 'vacunacion']) }}"
                                   title="Ver cartas de vacunacion"
                                   aria-label="Ver cartas de vacunacion de {{ $animal->name }}"
                                   class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-base text-slate-500 shadow-sm transition-all hover:border-teal-200 hover:bg-teal-50 hover:text-teal-700">
                                    &#128137;
                                </a>
                            </td>

                            {{-- Relación con el Dueño usando el Accessor full_name --}}
                            <td class="px-4 py-4">
                                @if($animal->customer)
                                    <a href="{{ route('client.customers.show', ['customer' => $animal->customer, 'tab' => 'mascotas']) }}" class="group inline-flex flex-col">
                                        <span class="text-xs font-black theme-text-heading transition-colors group-hover:text-[var(--theme-primary)] group-hover:underline decoration-2">{{ $animal->customer->full_name }}</span>
                                        <span class="text-[10px] text-slate-400 mt-0.5">{{ $animal->customer->phone ?: 'Sin telefono' }}</span>
                                    </a>
                                @else
                                    <span class="text-xs text-red-500 italic font-medium">Sin dueno asignado</span>
                                @endif
                            </td>

                            {{-- Peso --}}
                            <td class="px-4 py-4">
                                @if($animal->club)
                                    <a href="{{ route('client.clubes.edit', ['clube' => $animal->club, 'tab' => 'miembros']) }}"
                                       title="Ver miembros del club"
                                       class="inline-flex text-[9px] font-black uppercase tracking-widest theme-text-primary bg-teal-50 px-2.5 py-1 rounded-full transition-all hover:bg-teal-100 hover:shadow-sm">
                                        {{ $animal->club->name }}
                                    </a>
                                @else
                                    <span class="text-[10px] text-slate-400 font-bold uppercase">Sin club</span>
                                @endif
                            </td>

                            {{-- Peso --}}
                            <td class="px-4 py-4 text-xs font-medium text-slate-600">
                                {{ $animal->weight ? $animal->weight . ' kg' : '--' }}
                            </td>

                            <td class="px-4 py-4 text-center">
                                @if(filled($animal->allergies))
                                    <span
                                        title="{{ $animal->allergies }}"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-base text-amber-600 shadow-sm"
                                        aria-label="Alergias: {{ $animal->allergies }}"
                                    >
                                        &#9888;
                                    </span>
                                @else
                                    <span title="Sin alergias registradas" class="text-[10px] font-black uppercase tracking-widest text-slate-300">N/A</span>
                                @endif
                            </td>

                            {{-- Status Toggle Dinámico --}}
                            <td class="px-4 py-4">
                                <form action="{{ route('client.animals.toggle', $animal->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="flex items-center gap-2 group focus:outline-none"
                                            title="{{ $animal->status === 'active' ? 'Click para inactivar' : 'Click para activar' }}">
                                        <div class="w-8 h-5 flex items-center p-1 rounded-full transition-colors duration-300 {{ $animal->status === 'active' ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                            <div class="w-3 h-3 bg-white rounded-full shadow-sm transition-transform duration-300 transform {{ $animal->status === 'active' ? 'translate-x-3' : 'translate-x-0' }}"></div>
                                        </div>
                                        <span class="text-[9px] font-black uppercase tracking-widest {{ $animal->status === 'active' ? 'text-emerald-600' : 'text-slate-400' }}">
                                            {{ $animal->status === 'active' ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </button>
                                </form>
                            </td>

                            {{-- Acciones --}}
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('client.animals.edit', ['animal' => $animal, 'tab' => 'historial']) }}"
                                       class="inline-flex items-center justify-center theme-button-dark px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all">
                                        Detalles
                                    </a>
                                </div>
                            </td>
                             
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <p class="text-sm font-bold text-slate-400">No hay caballos registrados para los criterios de búsqueda.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-4">
            <div class="dynamic-pagination max-w-full overflow-x-auto">
                {{ $animals->links() }}
            </div>
        </div>

    </div>

    {{-- MODAL: NUEVA MASCOTA --}}
    {{-- MODAL: NUEVA MASCOTA --}}
<div x-show="animalModal" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 overflow-y-auto" 
     style="display: none;"
     x-data="{
        loading: false,
        customerQuery: '',
        selectedCustomer: null,
        customerSuggestions: [],
        searchCustomerUrl: '{{ route('client.api.buscar-clientes') }}',
        searchCustomers() {
            if (this.selectedCustomer || this.customerQuery.length < 2) {
                this.customerSuggestions = [];
                return;
            }

            fetch(`${this.searchCustomerUrl}?q=${encodeURIComponent(this.customerQuery)}`)
                .then(response => response.json())
                .then(data => { this.customerSuggestions = data; });
        },
        selectCustomer(customer) {
            this.selectedCustomer = customer;
            this.customerQuery = customer.full_name;
            this.customerSuggestions = [];
        },
        removeCustomer() {
            this.selectedCustomer = null;
            this.customerQuery = '';
            this.customerSuggestions = [];
        }
     }">
    
    <div class="flex items-center justify-center min-h-screen px-4 text-center sm:p-0">
        <div class="fixed inset-0 transition-opacity theme-overlay backdrop-blur-sm" @click="if(!loading) animalModal = false"></div>

        <div class="inline-block overflow-hidden text-left align-middle transition-all transform bg-white rounded-[24px] shadow-2xl sm:my-8 sm:max-w-2xl sm:w-full border border-slate-100 relative"
             x-show="animalModal"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0">
            
            {{-- SPINNER DE CARGA --}}
            <div x-show="loading" class="absolute inset-0 bg-white/80 backdrop-blur-md z-50 flex flex-col items-center justify-center gap-4" style="display: none;">
                <div class="w-10 h-10 border-4 border-slate-200 theme-spinner-primary rounded-full animate-spin"></div>
                <p class="text-[10px] font-black theme-text-heading uppercase tracking-[0.2em] animate-pulse">Guardando Caballo...</p>
            </div>

            {{-- Formulario --}}
            <form action="{{ route('client.animals.store') }}" method="POST" @submit="if (!selectedCustomer) { $event.preventDefault(); return; } loading = true">
                @csrf
                
                <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="text-lg font-black theme-text-heading tracking-tighter">Registrar Nueva Caballo</h3>
                    <button type="button" @click="animalModal = false" :disabled="loading" class="text-slate-400 hover:text-red-500 transition-colors">✕</button>
                </div>

                <div class="p-8 space-y-5">
                    {{-- Buscador del propietario --}}
                    <div class="space-y-2 relative">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Propietario / Dueño *</label>
                        <div class="relative">
                            <input type="text"
                                   x-model="customerQuery"
                                   @input.debounce.300ms="searchCustomers()"
                                   placeholder="Escribe nombre, apellido o telefono..."
                                   :disabled="selectedCustomer !== null"
                                   required
                                   class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 pr-36 text-sm font-semibold theme-text-heading placeholder-slate-400 focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-inner">

                            <template x-if="selectedCustomer">
                                <button type="button"
                                        @click="removeCustomer()"
                                        class="absolute right-3 top-2.5 px-3 py-1.5 rounded-lg bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest hover:bg-rose-100">
                                    Cambiar
                                </button>
                            </template>
                        </div>

                        <input type="hidden" name="customer_id" :value="selectedCustomer ? selectedCustomer.id : ''">

                        <div x-show="customerSuggestions.length > 0"
                             x-cloak
                             class="absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-xl overflow-hidden divide-y divide-slate-100">
                            <template x-for="customer in customerSuggestions" :key="customer.id">
                                <button type="button"
                                        @click="selectCustomer(customer)"
                                        class="w-full p-3 hover:bg-slate-50 transition-colors flex justify-between items-center text-left">
                                    <span>
                                        <span class="text-xs font-bold theme-text-heading block" x-text="customer.full_name"></span>
                                        <span class="text-[10px] text-slate-400 font-medium" x-text="customer.phone || 'Sin telefono'"></span>
                                    </span>
                                    <span class="text-[9px] theme-bg-primary-soft theme-text-primary font-black px-2 py-1 rounded-full uppercase tracking-wider" x-text="customer.animals.length + ' mascotas'"></span>
                                </button>
                            </template>
                        </div>

                        <p x-show="customerQuery.length > 0 && customerQuery.length < 2 && !selectedCustomer" x-cloak class="text-[11px] text-slate-400 font-semibold">
                            Escribe al menos 2 caracteres para buscar.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Nombre Mascota --}}
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Nombre del Caballo *</label>
                            <input type="text" name="name" required placeholder="Ej. Rocko" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-inner">
                        </div>

                        {{-- Tipo de Animal / Especie --}}
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Especie / Tipo de Animal *</label>
                            @php
                                $defaultAnimalTypeId = old('animal_type_id', optional($animalTypes->first())->id);
                            @endphp
                            <select name="animal_type_id" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-inner cursor-pointer">
                                <option value="" disabled {{ blank($defaultAnimalTypeId) ? 'selected' : '' }}>Selecciona una especie...</option>
                                @foreach($animalTypes as $type)
                                    <option value="{{ $type->id }}" {{ (string) $defaultAnimalTypeId === (string) $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                                @endforeach
                            </select>
                            
                            @if($animalTypes->isEmpty())
                                <p class="text-[11px] text-amber-600 font-medium mt-1">
                                    ⚠️ No tienes especies registradas. Configúralas en <a href="{{ route('client.mi-configuracion.index') }}" class="underline font-bold">Configuración</a>.
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Club</label>
                        <select name="club_id" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-inner cursor-pointer">
                            <option value="">Sin club</option>
                            @foreach($clubs as $club)
                                <option value="{{ $club->id }}">{{ $club->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- GRID DE 3 COLUMNAS: SEXO, FECHA, PESO --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {{-- Sexo (Añadido Obligatorio) --}}
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Sexo *</label>
                            <select name="sex" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-inner cursor-pointer">
                                <option value="" disabled selected>Elegir...</option>
                                <option value="male">Macho (♂)</option>
                                <option value="female">Hembra (♀)</option>
                                <option value="unknown">Desconocido</option>
                            </select>
                        </div>

                        {{-- Fecha de Nacimiento (Corregido a birthdate) --}}
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">F. de Nacimiento</label>
                            <input type="date" name="birthdate" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-inner">
                        </div>

                        {{-- Peso --}}
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Peso (kg)</label>
                            <input type="number" step="0.01" name="weight" placeholder="Ej. 12.5" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-inner">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Color</label>
                            <input type="text" name="color" placeholder="Ej. Cafe con blanco" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-inner">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Microchip</label>
                            <input type="text" name="microchip" inputmode="numeric" pattern="[0-9]{15}" maxlength="15" placeholder="15 digitos numericos" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-inner">
                            <p class="text-[10px] font-semibold text-slate-400">Solo se aceptan 15 numeros.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Notas Clinicas</label>
                            <textarea name="notes" rows="3" placeholder="Ej. Comportamiento nervioso, indicaciones generales..." class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none resize-none shadow-inner"></textarea>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Alergias</label>
                            <textarea name="allergies" rows="3" placeholder="Ej. Penicilina, pollo, anestesia..." class="w-full bg-amber-50/60 border border-amber-100 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white focus:border-amber-300 focus:ring-4 focus:ring-amber-100 transition-all outline-none resize-none shadow-inner"></textarea>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-6 bg-slate-50 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="animalModal = false" :disabled="loading" class="text-xs font-black uppercase tracking-widest text-slate-400 hover:text-slate-600">Cancelar</button>
                    <button type="submit" :disabled="loading" class="theme-surface-dark px-6 py-3.5 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg">Guardar Caballo</button>
                </div>
            </form>
        </div>
    </div>
</div>

</div>
@endsection

