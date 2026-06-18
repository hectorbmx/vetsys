@extends('layouts.client')

@section('title', 'Pacientes')

@section('contextual-tour', 'animals')

@section('content')
<div class="space-y-8" x-data="{ animalModal: false }">
    
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
    
    {{-- HEADER DE LA VISTA --}}
    <div data-tour="animals-header" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black theme-text-heading tracking-tighter">Gestión de Pacientes</h1>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Administra los pacientes de tu clínica y sus historiales.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
            <form method="GET" action="{{ route('client.animals.index') }}" class="relative w-full sm:w-80">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 text-xs">🔍</span>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar paciente, especie o dueño..." class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-12 py-3.5 text-xs font-semibold theme-text-heading placeholder-slate-400 theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-sm">
                @if(request()->filled('q'))
                    <a href="{{ route('client.animals.index', ['per_page' => $perPage]) }}" class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-rose-500 text-xs font-black">x</a>
                @endif
            </form>
            <button data-tour="add-animal" @click="animalModal = true" class="inline-flex items-center justify-center gap-2 theme-surface-dark px-5 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg shadow-slate-200 transition-all group whitespace-nowrap">
                <span class="text-sm transition-transform group-hover:scale-125">+</span>
                Nuevo Paciente
            </button>
        </div>
    </div>

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

    {{-- CONTENEDOR DE BASE DE DATOS --}}
    <div data-tour="animals-list" class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        
        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Database de Pacientes</h3>
            <div class="flex flex-wrap items-center justify-end gap-3">
                @if(request()->filled('q'))
                    <span class="text-[11px] font-bold text-slate-400">Filtro: {{ request('q') }}</span>
                @endif
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
            </div>
        </div>

        {{-- TABLA DE MASCOTAS --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/20">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Nombre</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Specie</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Dueño</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Club</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Peso</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Detalles</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($animals as $animal)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            {{-- Info Básica del Animal --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl theme-bg-primary-soft theme-text-primary flex items-center justify-center font-black text-sm">
                                        {{ substr($animal->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold theme-text-heading leading-tight">{{ $animal->name }}</h4>
                                        <p class="text-[10px] font-medium text-slate-400 mt-0.5">
                                            Edad: {{ $animal->birthdate ? $animal->birthdate->age . ' años' : 'No registrada' }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            {{-- Especie y Raza --}}
                            <td class="px-6 py-4">
                                <span class="text-xs font-bold theme-text-heading block">{{ $animal->animalType->name ?? 'Sin especie' }}</span>
                                <span class="text-[10px] text-slate-400 font-semibold block mt-0.5">{{ $animal->color ?? 'Color no registrado' }}</span>
                            </td>

                            {{-- Relación con el Dueño usando el Accessor full_name --}}
                            <td class="px-6 py-4">
                                @if($animal->customer)
                                    <div class="text-xs font-bold theme-text-heading">{{ $animal->customer->full_name }}</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">{{ $animal->customer->phone }}</div>
                                @else
                                    <span class="text-xs text-red-500 italic font-medium">Sin dueño asignado</span>
                                @endif
                            </td>

                            {{-- Peso --}}
                            <td class="px-6 py-4">
                                @if($animal->club)
                                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest theme-text-primary theme-bg-primary-soft px-2.5 py-1 rounded-full">
                                        {{ $animal->club->name }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400 font-semibold">Sin club</span>
                                @endif
                            </td>

                            {{-- Peso --}}
                            <td class="px-6 py-4 text-xs font-bold theme-text-heading">
                                {{ $animal->weight ? $animal->weight . ' kg' : '--' }}
                            </td>

                            {{-- Status Toggle Dinámico --}}
                   <td class="px-6 py-4">
    <form action="{{ route('client.animals.toggle', $animal->id) }}" method="POST">
        @csrf
        @method('PATCH')
        <button type="submit" 
                class="flex items-center gap-2 group focus:outline-none"
                title="{{ $animal->status === 'active' ? 'Click para Inactivar' : 'Click para Activar' }}">
            
            <div class="w-10 h-6 flex items-center p-1 rounded-full transition-colors duration-300 {{ $animal->status === 'active' ? 'theme-bg-primary' : 'bg-slate-300' }}">
                <div class="w-4 h-4 bg-white rounded-full shadow-sm transition-transform duration-300 transform {{ $animal->status === 'active' ? 'translate-x-4' : 'translate-x-0' }}"></div>
            </div>
            
            <span class="text-[10px] font-bold uppercase tracking-wider min-w-[50px] {{ $animal->status === 'active' ? 'theme-text-primary-strong' : 'text-slate-400' }}">
                {{ ucfirst($animal->status ?? 'inactive') }}
            </span>
        </button>
    </form>
</td>

                            {{-- Acciones --}}
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    
                                    <!-- <a href="{{ route('client.animals.edit', $animal) }}" class="inline-flex items-center justify-center theme-button-dark px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all" title="Editar">🔍</a> -->
                                    <a href="{{ route('client.animals.edit',$animal) }}" 
                                        class="p-1.5 text-slate-400 theme-hover-text-primary transition-colors"
                                        title="Ver ficha">🔍</a>
                                </div>
                            </td>
                             
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <p class="text-sm font-bold text-slate-400">No hay pacientes registrados para los criterios de búsqueda.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINACIÓN LARAVEL --}}
        <div class="p-6 border-t border-slate-100 bg-slate-50/30">
            {{ $animals->links() }}
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
                <p class="text-[10px] font-black theme-text-heading uppercase tracking-[0.2em] animate-pulse">Guardando Paciente...</p>
            </div>

            {{-- Formulario --}}
            <form action="{{ route('client.animals.store') }}" method="POST" @submit="if (!selectedCustomer) { $event.preventDefault(); return; } loading = true">
                @csrf
                
                <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="text-lg font-black theme-text-heading tracking-tighter">Registrar Nueva Mascota (Paciente)</h3>
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
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Nombre del Paciente *</label>
                            <input type="text" name="name" required placeholder="Ej. Rocko" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-inner">
                        </div>

                        {{-- Tipo de Animal / Especie --}}
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Especie / Tipo de Animal *</label>
                            <select name="animal_type_id" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-inner cursor-pointer">
                                <option value="" disabled selected>Selecciona una especie...</option>
                                @foreach($animalTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
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

                    {{-- Notas Clínicas --}}
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Notas Clínicas / Alergias</label>
                        <textarea name="notes" rows="3" placeholder="Ej. Alérgico a la penicilina, comportamiento nervioso..." class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none resize-none shadow-inner"></textarea>
                    </div>
                </div>

                <div class="px-8 py-6 bg-slate-50 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="animalModal = false" :disabled="loading" class="text-xs font-black uppercase tracking-widest text-slate-400 hover:text-slate-600">Cancelar</button>
                    <button type="submit" :disabled="loading" class="theme-surface-dark px-6 py-3.5 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg">Guardar Paciente</button>
                </div>
            </form>
        </div>
    </div>
</div>

</div>
@endsection
