@extends('layouts.client')

@section('title', 'Configuración General')

@section('contextual-tour', 'configuration')

@section('content')
{{-- Centralizamos el estado de las pestañas y del modal de especies con Alpine --}}
<div class="space-y-8" x-data="{
    currentTab: @js(request('tab', session('activeTab', 'animales'))),
    typeModal: false,
    selectedThemePalette: @js($activeThemePalette),
    savedThemePalette: @js($activeThemePalette),
    previewThemePalette(palette) {
        this.selectedThemePalette = palette;
        document.body.dataset.themePalette = palette;
    },
    restoreThemePalette() {
        this.previewThemePalette('ocean');
    },
    cancelThemePreview() {
        this.previewThemePalette(this.savedThemePalette);
    }
}">
    
    {{-- SISTEMA DE TOASTS FLOTANTES --}}
    <div class="fixed top-4 right-4 z-[99] space-y-3 min-w-[320px]">
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition class="bg-white border-l-4 border-emerald-500 rounded-xl shadow-xl p-4 flex items-center justify-between border border-slate-100">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold">✓</span>
                    <div>
                        <p class="text-xs font-black theme-text-heading uppercase tracking-wider">Configuración</p>
                        <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ session('success') }}</p>
                    </div>
                </div>
                <button @click="show = false" class="text-slate-400 hover:text-slate-600 text-xs ml-4">✕</button>
            </div>
        @endif

      @if(session('error') || $errors->any())
    <div x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 8000)"
         x-transition
         class="bg-white border-l-4 border-red-500 rounded-xl shadow-xl p-4 flex items-start justify-between border border-slate-100">

        <div class="flex items-start gap-3">
            <span class="w-7 h-7 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-sm font-bold">
                ✕
            </span>

            <div>
                <p class="text-xs font-black theme-text-heading uppercase tracking-wider">
                    Error de validación
                </p>

                @if(session('error'))
                    <p class="text-[11px] text-slate-500 font-semibold mt-0.5">
                        {{ session('error') }}
                    </p>
                @endif

                @if($errors->any())
                    <ul class="mt-1 space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li class="text-[11px] text-slate-500 font-semibold">
                                • {{ $error }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <button @click="show = false"
                class="text-slate-400 hover:text-slate-600 text-xs ml-4">
            ✕
        </button>
    </div>
@endif
    </div>

    {{-- HEADER --}}
    <div data-tour="configuration-header">
        <h1 class="text-3xl font-black theme-text-heading tracking-tighter">Panel de Configuración</h1>
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Inicializa los catálogos primarios, usuarios y finanzas de tu clínica.</p>
    </div>

    {{-- TABS DE CONTROL --}}
    <div class="flex border-b border-slate-200 gap-2 overflow-x-auto">
        <button data-tour="animal-type-tab" @click="currentTab = 'animales'"
                :class="currentTab === 'animales' ? 'theme-tab-active' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            🐕 Tipos de Animales
        </button>
        <button @click="currentTab = 'usuarios'" 
                :class="currentTab === 'usuarios' ? 'theme-tab-active' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            👥 Usuarios / Equipo
        </button>
        <button @click="currentTab = 'bancos'" 
                :class="currentTab === 'bancos' ? 'theme-tab-active' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            💳 Cuentas Bancarias
        </button>
        {{-- Nuevo Tab para Métodos de Pago --}}
        <button data-tour="payment-method-tab" @click="currentTab = 'pagos'"
                :class="currentTab === 'pagos' ? 'theme-tab-active' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            💰 Métodos de Pago
        </button>
        <button @click="currentTab = 'apariencia'"
                :class="currentTab === 'apariencia' ? 'theme-tab-active' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            🎨 Apariencia
        </button>
        <button @click="currentTab = 'facturacion'"
                :class="currentTab === 'facturacion' ? 'theme-tab-active' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            🧾 Plan y Pagos
        </button>
         <!-- <button @click="currentTab = 'roles'"
                :class="currentTab === 'roles' ? 'theme-tab-active' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            🧾 Roles
        </button> -->
        <button @click="currentTab = 'importar'"
                :class="currentTab === 'importar' ? 'theme-tab-active' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            ⬆️ Importar Catalogos
        </button>

        <!-- <button @click="currentTab = 'facturar'"
                :class="currentTab === 'facturar' ? 'theme-tab-active' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
              💰  facturacion
        </button> -->
    </div>

    {{-- CONTENIDO DE LAS PESTAÑAS --}}

    {{-- TAB: APARIENCIA --}}
    <div x-show="currentTab === 'apariencia'" x-transition:enter="transition duration-200" class="space-y-6" x-cloak>
        <form action="{{ route('client.mi-configuracion.appearance.update') }}" method="POST" enctype="multipart/form-data" class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
            @csrf
            @method('PATCH')
            <input type="hidden" name="theme_palette" :value="selectedThemePalette">

            <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Apariencia del panel</h3>
                    <p class="text-[11px] text-slate-400 font-medium mt-0.5">La paleta se aplica a todos los usuarios de esta clinica.</p>
                </div>
                <span class="inline-flex rounded-xl theme-bg-primary-soft px-3 py-2 text-[10px] font-black uppercase tracking-widest theme-text-primary-strong">
                    <span x-text="selectedThemePalette"></span>
                </span>
            </div>

            <div class="p-6 border-b border-slate-100 grid grid-cols-1 lg:grid-cols-[240px_1fr] gap-6">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest theme-text-heading">Logo del tenant</p>
                    <p class="text-[11px] text-slate-400 font-medium mt-1">Se muestra en el panel lateral de todos los usuarios.</p>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    @php($tenantLogoUrl = $tenant?->logoUrl())
                    <div class="h-20 w-20 rounded-2xl border border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden">
                        @if($tenantLogoUrl)
                            <img src="{{ $tenantLogoUrl }}" alt="{{ $tenant->name }}" class="h-full w-full object-contain p-2">
                        @else
                            <span class="h-12 w-12 rounded-xl theme-bg-primary theme-text-primary-ink flex items-center justify-center font-black text-lg">
                                {{ substr($tenant->name ?? 'V', 0, 1) }}
                            </span>
                        @endif
                    </div>

                    <div class="flex-1 space-y-3">
                        <input type="file"
                               name="logo"
                               accept="image/png,image/jpeg,image/webp"
                               @disabled(!$canManageAppearance)
                               class="block w-full text-xs font-semibold text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:px-4 file:py-2.5 file:text-[10px] file:font-black file:uppercase file:tracking-widest theme-file-input disabled:opacity-50">

                        <div class="flex flex-wrap items-center gap-3">
                            <p class="text-[10px] font-semibold text-slate-400">PNG, JPG o WebP. Max 2 MB.</p>
                            @if($tenant?->logo && $canManageAppearance)
                                <label class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-500">
                                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 theme-text-primary focus:ring-0">
                                    Quitar logo
                                </label>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                @foreach($themePalettes as $key => $palette)
                    <button type="button"
                            @click="previewThemePalette(@js($key))"
                            :class="selectedThemePalette === @js($key) ? 'theme-border-primary ring-4 theme-ring-primary' : 'border-slate-200 hover:border-slate-300'"
                            class="text-left rounded-2xl border bg-white p-4 transition-all shadow-sm focus:outline-none theme-focus-primary">
                        <span class="flex items-center gap-3">
                            <span class="flex h-11 w-11 overflow-hidden rounded-xl border border-slate-100">
                                <span class="h-full w-1/2" style="background-color: {{ $palette['sidebar'] }}"></span>
                                <span class="h-full w-1/2" style="background-color: {{ $palette['primary'] }}"></span>
                            </span>
                            <span>
                                <span class="block text-xs font-black uppercase tracking-widest theme-text-heading">{{ $palette['label'] }}</span>
                                <span class="mt-1 block text-[11px] font-semibold text-slate-400">{{ $palette['description'] }}</span>
                            </span>
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="px-6 py-5 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                @if($canManageAppearance)
                    <p class="text-[11px] font-semibold text-slate-400">Vista previa activa hasta guardar o cancelar.</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="cancelThemePreview()" class="rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-100 transition-colors">
                            Cancelar vista previa
                        </button>
                        <button type="button" @click="restoreThemePalette()" class="rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest theme-text-heading hover:bg-slate-100 transition-colors">
                            Restaurar predeterminada
                        </button>
                        <button type="submit" class="theme-button-primary rounded-xl px-5 py-2.5 text-[10px] font-black uppercase tracking-widest transition-all">
                            Aplicar paleta
                        </button>
                    </div>
                @else
                    <p class="text-[11px] font-semibold text-slate-400">Solo un administrador o responsable del tenant puede cambiar la apariencia.</p>
                @endif
            </div>
        </form>
    </div>
    
    {{-- TAB 1: TIPOS DE ANIMALES --}}
    <div x-show="currentTab === 'animales'" x-transition:enter="transition duration-200" class="space-y-6">
        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div>
                    <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Especies Clínicas Habilitadas</h3>
                    <p class="text-[11px] text-slate-400 font-medium mt-0.5">Registra los tipos de animales que tu personal puede atender en las fichas médicas.</p>
                </div>
                <button data-tour="add-animal-type" @click="typeModal = true" class="theme-button-dark px-4 py-2.5 rounded-xl font-bold text-xs tracking-wide shadow-sm transition-all theme-focus-primary">
                    + Agregar Tipo
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/10">
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Especie / Identificador</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Slug (Sistema)</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Descripción</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($animalTypes as $type)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg theme-bg-primary-soft theme-text-primary flex items-center justify-center font-black text-xs">
                                            🐾
                                        </div>
                                        <span class="text-xs font-bold theme-text-heading">{{ $type->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-xs font-mono text-slate-400">{{ $type->slug }}</td>
                                <td class="px-6 py-4 text-xs text-slate-500 max-w-xs truncate">{{ $type->description ?? 'Sin descripción añadida.' }}</td>
                                <td class="px-6 py-4">
                                    <form action="{{ route('client.mi-configuracion.toggle', $type->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" 
                                            class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none theme-focus-primary {{ $type->is_active ? 'theme-bg-primary' : 'bg-slate-200' }}">
                                            <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $type->is_active ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                        </button>
                                        <span class="ml-2 text-[9px] font-black uppercase tracking-widest {{ $type->is_active ? 'text-emerald-700' : 'text-slate-400' }}">
                                            {{ $type->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </form>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <!-- <button class="p-1.5 text-slate-400 theme-link-primary transition-colors" title="Editar Campos Dinámicos">⚙️ Campos</button> -->
                                    <a href="{{ route('client.mi-configuracion.fields.index', $type->id) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 border border-slate-200 hover:border-slate-300 rounded-lg text-xs font-semibold theme-text-heading transition-colors shadow-sm theme-focus-primary">
                                        ⚙️ Campos
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-sm font-bold text-slate-400">
                                    No has dado de alta ningún tipo de animal. Dale clic a "+ Agregar Tipo".
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- TAB 2: USUARIOS / EQUIPO --}}
    <div x-show="currentTab === 'usuarios'" x-transition:enter="transition duration-200" class="space-y-6" style="display: none;">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/50">
                    <div>
                        <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Usuarios del equipo</h3>
                        <p class="text-[11px] text-slate-400 font-medium mt-0.5">
                            {{ $usersUsed }} de {{ is_null($maxUsers) ? 'ilimitados' : $maxUsers }} usuarios incluidos en {{ $tenant?->plan?->name ?? 'tu plan' }}.
                        </p>
                    </div>
                    <span class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-[10px] font-black uppercase tracking-widest {{ $canInviteUsers ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                        {{ $canInviteUsers ? 'Cupo disponible' : 'Limite alcanzado' }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/10">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Usuario</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Rol</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Invitacion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($teamUsers as $teamUser)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl theme-bg-primary-soft theme-text-primary flex items-center justify-center font-black text-xs">
                                                {{ strtoupper(substr($teamUser->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <p class="text-xs font-black theme-text-heading">{{ $teamUser->name }}</p>
                                                <p class="text-[11px] text-slate-400 font-semibold">{{ $teamUser->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @php($roleName = $teamUser->roles->pluck('name')->first())
                                        <span class="inline-flex text-[9px] font-black uppercase tracking-widest bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full">
                                            {{ $roleOptions[$roleName] ?? ($roleName ?? 'Sin rol') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex text-[9px] font-black uppercase tracking-widest {{ $teamUser->is_active ? 'text-emerald-700 bg-emerald-50' : 'text-amber-700 bg-amber-50' }} px-2.5 py-1 rounded-full">
                                            {{ $teamUser->is_active ? 'Activo' : 'Pendiente' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-slate-500 font-semibold">
                                        {{ $teamUser->invitation_accepted_at ? $teamUser->invitation_accepted_at->format('d/m/Y') : 'Sin aceptar' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-sm font-bold text-slate-400">
                                        Todavia no hay usuarios registrados para este tenant.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                @if($canInviteUsers && $canManageTeam)
                    <form action="{{ route('client.mi-configuracion.users.store') }}" method="POST" class="p-6 space-y-5">
                        @csrf
                        <div>
                            <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Invitar usuario</h3>
                            <p class="text-[11px] text-slate-400 font-medium mt-1">Se enviara un correo para que configure su contrasena.</p>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Nombre *</label>
                            <input type="text" name="name" value="{{ old('name') }}" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading outline-none focus:ring-4 transition-all theme-input">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Correo *</label>
                            <input type="email" name="email" value="{{ old('email') }}" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading outline-none focus:ring-4 transition-all theme-input">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Rol *</label>
                            <select name="role" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading outline-none focus:ring-4 transition-all theme-input">
                                @foreach($roleOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="w-full theme-button-dark px-6 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] shadow-lg theme-focus-primary">
                            Enviar invitacion
                        </button>
                    </form>
                @elseif(!$canManageTeam)
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center text-2xl mx-auto mb-4">!</div>
                        <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Sin permisos</h3>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto mt-2">
                            Solo un Administrador puede invitar usuarios y asignar roles dentro del tenant.
                        </p>
                    </div>
                @else
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center text-2xl mx-auto mb-4">+</div>
                        <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Mejora tu plan</h3>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto mt-2">
                            Tu plan actual incluye {{ $maxUsers }} usuario{{ (int) $maxUsers === 1 ? '' : 's' }}. Para agregar mas equipo, cambia a un plan con mas usuarios.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- TAB 3: BANCOS (MAQUETA) --}}
    <div x-show="currentTab === 'bancos'" x-transition:enter="transition duration-200" class="space-y-6" style="display: none;">
        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm p-8 text-center max-w-2xl mx-auto">
            <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">💳</div>
            <h3 class="text-lg font-black theme-text-heading uppercase tracking-widest">Cobros con Stripe Connect</h3>
            <p class="text-sm text-slate-500 mt-2 mb-8 px-4">
                Configura tu cuenta de Stripe para recibir los pagos de tus clientes directamente en tu cuenta bancaria. 
                Nuestra integración te permite procesar tarjetas y gestionar cobros de forma segura.
            </p>

            @if($tenant->stripe_onboarding_completed)
                <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-6 flex flex-col items-center">
                    <div class="flex items-center gap-2 text-emerald-600 mb-2">
                        <span class="text-xl">✅</span>
                        <p class="text-xs font-black uppercase tracking-widest">Cuenta Conectada</p>
                    </div>
                    <p class="text-xs text-emerald-700 font-semibold mb-6">Tu clínica ya está habilitada para recibir pagos con Stripe.</p>
                    
                    <form action="{{ route('client.stripe-connect.connect') }}" method="POST">
                        @csrf
                        <button type="submit" class="text-xs font-bold text-slate-500 hover:text-slate-800 underline">
                            Ir al Dashboard de Stripe
                        </button>
                    </form>
                </div>
            @else
                <div class="bg-slate-50 border border-slate-100 rounded-2xl p-8">
                    <form action="{{ route('client.stripe-connect.connect') }}" method="POST">
                        @csrf
                        <button type="submit" class="bg-[#635BFF] hover:bg-[#5148d8] text-white px-8 py-4 rounded-xl font-black text-sm uppercase tracking-widest transition-all shadow-lg shadow-[#635BFF]/20 flex items-center gap-3 mx-auto">
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M13.911 8.184c-.732 0-1.242.348-1.242.947 0 .584.773.834 1.517 1.014 1.139.27 2.628.618 2.628 2.384 0 1.901-1.554 2.879-3.411 2.879-1.305 0-2.529-.304-3.447-.742v-2.42c.864.507 2.181.822 3.105.822.684 0 1.224-.315 1.224-.877 0-.585-.81-.844-1.62-.99-1.206-.247-2.52-.63-2.52-2.317 0-1.631 1.431-2.812 3.231-2.812 1.107 0 2.214.236 3.015.63v2.34a5.55 5.55 0 0 0-2.481-.861zm-7.669 4.887c0 .619.468 1.091 1.091 1.091.611 0 1.08-.472 1.08-1.091 0-.611-.469-1.08-1.08-1.08-.623 0-1.091.469-1.091 1.08zm1.091-3.668c-1.395 0-2.541 1.151-2.541 2.587 0 1.444 1.146 2.595 2.541 2.595 1.404 0 2.559-1.151 2.559-2.595 0-1.436-1.155-2.587-2.559-2.587zm11.751 3.668c0 .619.468 1.091 1.091 1.091.611 0 1.08-.472 1.08-1.091 0-.611-.469-1.08-1.08-1.08-.623 0-1.091.469-1.091 1.08zm1.091-3.668c-1.395 0-2.541 1.151-2.541 2.587 0 1.444 1.146 2.595 2.541 2.595 1.404 0 2.559-1.151 2.559-2.595 0-1.436-1.155-2.587-2.559-2.587zM.164 12c0-6.537 5.3-11.837 11.836-11.837s11.836 5.3 11.836 11.837c0 6.536-5.3 11.836-11.836 11.836S.164 18.536.164 12z"/></svg>
                            Conectar con Stripe
                        </button>
                    </form>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-6">Serás redirigido a Stripe para completar tu perfil.</p>
                </div>
            @endif
        </div>
    </div>
    @include('client.mi-configuracion.payment-methods.index')

    {{-- TAB 5: PLAN Y PAGOS --}}
    <div x-show="currentTab === 'facturacion'" x-transition:enter="transition duration-200" class="space-y-6" style="display: none;">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 theme-surface-dark rounded-[24px] p-6 shadow-xl shadow-slate-200 overflow-hidden relative">
                <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full theme-bg-primary-soft-hover"></div>
                <div class="relative">
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] theme-text-primary">Plan actual</p>
                    <h3 class="text-3xl font-black mt-4">{{ $tenant?->plan?->name ?? 'Sin plan' }}</h3>
                    <p class="text-sm font-semibold text-slate-300 mt-2">{{ $tenant?->plan?->description ?? 'Sin descripcion disponible.' }}</p>

                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl bg-white/10 p-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Usuarios</p>
                            <p class="text-xl font-black mt-1">{{ $tenant?->plan?->max_users ?? 'Sin limite' }}</p>
                        </div>
                        <div class="rounded-2xl bg-white/10 p-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Clientes</p>
                            <p class="text-xl font-black mt-1">{{ $tenant?->plan?->max_clients ?? 'Sin limite' }}</p>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl bg-white/10 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Precio</p>
                        <p class="text-2xl font-black mt-1">
                            @if($tenant?->plan)
                                ${{ number_format($tenant->plan->price, 2) }} {{ $tenant->plan->currency }} / {{ $tenant->plan->billing_period }}
                            @else
                                --
                            @endif
                        </p>
                    </div>

                    <div class="mt-3 rounded-2xl bg-white/10 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Vigencia actual</p>
                        <p class="text-lg font-black mt-1">
                            {{ $tenant?->subscription_ends_at ? $tenant->subscription_ends_at->format('d/m/Y') : 'Sin fecha registrada' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Contratar otro plan</h3>
                    <p class="text-[11px] text-slate-400 font-medium mt-0.5">Elige un plan activo del catalogo. El cambio queda pendiente hasta confirmar el pago.</p>
                </div>

                @if($pendingPlanRequest)
                    <div class="mx-6 mt-6 rounded-2xl border border-amber-100 bg-amber-50 p-4">
                        <p class="text-xs font-black text-amber-900">Solicitud pendiente</p>
                        <p class="text-[11px] font-semibold text-amber-700 mt-1">
                            Tienes una renovacion pendiente para {{ $pendingPlanRequest->plan?->name ?? 'otro plan' }}.
                            Inicia {{ optional($pendingPlanRequest->starts_at)->format('d/m/Y') ?? '--' }} y vence {{ optional($pendingPlanRequest->ends_at)->format('d/m/Y') ?? '--' }}.
                            Metodo: {{ $pendingPlanPayment?->payment_method ? str_replace('_', ' ', $pendingPlanPayment->payment_method) : 'manual' }}.
                        </p>
                    </div>
                @endif

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    @forelse($activePlans as $plan)
                        <div class="border border-slate-200 rounded-2xl p-5 {{ $tenant?->plan_id === $plan->id ? 'bg-slate-50' : 'bg-white' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="text-base font-black theme-text-heading">{{ $plan->name }}</h4>
                                    <p class="text-[11px] text-slate-400 font-semibold mt-1 line-clamp-2">{{ $plan->description ?? 'Sin descripcion.' }}</p>
                                </div>
                                @if($tenant?->plan_id === $plan->id)
                                    <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-widest text-emerald-700">Actual</span>
                                @endif
                            </div>

                            <p class="text-2xl font-black theme-text-heading mt-4">${{ number_format($plan->price, 2) }} <span class="text-[10px] text-slate-400 uppercase">{{ $plan->currency }}</span></p>
                            <div class="mt-4 flex flex-wrap gap-2 text-[10px] font-black uppercase tracking-widest text-slate-500">
                                <span class="rounded-lg bg-slate-100 px-2.5 py-1">{{ $plan->billing_period }}</span>
                                <span class="rounded-lg bg-slate-100 px-2.5 py-1">{{ $plan->max_users ?? 'Sin limite' }} usuarios</span>
                                <span class="rounded-lg bg-slate-100 px-2.5 py-1">{{ $plan->max_clients ?? 'Sin limite' }} clientes</span>
                            </div>

                            <form action="{{ route('client.mi-configuracion.plan.request') }}" method="POST" class="mt-5 space-y-3">
                                @csrf
                                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                <div class="grid grid-cols-1 gap-2">
                                    <select name="payment_method" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold theme-text-heading focus:outline-none theme-input">
                                        <option value="card_manual">Tarjeta de credito manual</option>
                                        <option value="transfer">Transferencia</option>
                                        <option value="cash">Efectivo</option>
                                        <option value="other">Otro</option>
                                    </select>
                                    <input type="text" name="payment_reference" placeholder="Referencia opcional, no escribas datos de tarjeta" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-semibold theme-text-heading focus:outline-none theme-input">
                                </div>
                                <button type="submit"
                                        class="w-full px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all theme-button-dark shadow-sm theme-focus-primary">
                                    {{ $tenant?->plan_id === $plan->id ? 'Renovar plan actual' : 'Contratar renovacion manual' }}
                                </button>
                            </form>

                            @if($plan->stripe_price_id)
                                <form action="{{ route('client.mi-configuracion.plan.stripe-checkout') }}" method="POST" class="mt-3">
                                    @csrf
                                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                    <button type="submit" class="w-full px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-[#635BFF] text-white hover:bg-[#5148d8] shadow-sm">
                                        Pagar con Stripe
                                    </button>
                                </form>
                            @else
                                <p class="mt-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Stripe no sincronizado</p>
                            @endif
                        </div>
                    @empty
                        <div class="md:col-span-2 px-6 py-12 text-center text-sm font-bold text-slate-400">
                            No hay planes activos disponibles por ahora.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50">
                <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Historial de pagos de suscripcion</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] text-slate-400 uppercase tracking-widest border-b border-slate-100">
                            <th class="px-6 py-3">Fecha</th>
                            <th class="px-6 py-3">Plan</th>
                            <th class="px-6 py-3">Metodo</th>
                            <th class="px-6 py-3">Referencia</th>
                            <th class="px-6 py-3">Estado</th>
                            <th class="px-6 py-3 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse($subscriptionPayments as $payment)
                            <tr>
                                <td class="px-6 py-4 text-xs font-bold text-slate-600">{{ optional($payment->paid_at)->format('d/m/Y') ?? $payment->created_at->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 text-xs font-bold theme-text-heading">{{ $payment->plan?->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 text-xs font-semibold text-slate-500">{{ $payment->payment_method ?? ucfirst($payment->provider) }}</td>
                                <td class="px-6 py-4 text-xs font-semibold text-slate-400">{{ $payment->payment_reference ?? $payment->provider_invoice_id ?? '--' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest {{ $payment->status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $payment->status }}</span>
                                </td>
                                <td class="px-6 py-4 text-xs font-black text-right theme-text-heading">${{ number_format($payment->amount, 2) }} {{ $payment->currency }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm font-bold text-slate-400">Sin pagos de suscripcion registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- TAB 6: ROLES --}}
    <div x-show="currentTab === 'roles'" x-transition:enter="transition duration-200" class="space-y-6" style="display: none;">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Roles del tenant</h3>
                    <p class="text-[11px] text-slate-400 font-medium mt-0.5">
                        Estos roles son fijos para operar la clinica. El rol super-admin pertenece solo al dueno del SaaS.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/10">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Rol</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Descripcion</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Usuarios</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($roleOptions as $roleValue => $roleLabel)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="inline-flex text-[9px] font-black uppercase tracking-widest bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full">
                                            {{ $roleLabel }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-xs font-semibold text-slate-500">
                                        {{ $roleDescriptions[$roleValue] ?? 'Rol operativo del tenant.' }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-xs font-black theme-text-heading">
                                        {{ $teamUsers->filter(fn ($teamUser) => $teamUser->roles->pluck('name')->contains($roleValue))->count() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="theme-surface-dark rounded-[24px] p-6 shadow-xl shadow-slate-200">
                <p class="text-[10px] font-black uppercase tracking-[0.24em] theme-text-primary">Regla SaaS</p>
                <h3 class="text-xl font-black mt-4">Roles controlados</h3>
                <p class="text-xs font-semibold text-slate-300 mt-3 leading-6">
                    Por seguridad no permitimos crear roles libres desde el tenant. Asi evitamos que un cliente genere permisos superiores o mezcle roles del SaaS con roles operativos.
                </p>
                <div class="mt-5 rounded-2xl bg-white/10 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">No disponible para tenants</p>
                    <p class="text-sm font-black mt-1">super-admin</p>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 7: IMPORTAR CATALOGOS --}}
    <div x-show="currentTab === 'importar'" x-transition:enter="transition duration-200" class="space-y-6" style="display: none;">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Importar clientes legacy</h3>
                    <p class="text-[11px] text-slate-400 font-medium mt-0.5">
                        Carga el CSV exportado desde la tabla legacy de usuarios. Se importara al tenant #{{ auth()->user()->tenant_id }}.
                    </p>
                </div>

                <form action="{{ route('client.mi-configuracion.import-customers') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                    @csrf

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Columnas esperadas</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach(['ClienteID', 'Nombre', 'AP', 'AM', 'Correo', 'Telefono', 'created_at', 'estatus'] as $column)
                                <span class="rounded-lg bg-white border border-slate-200 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest theme-text-heading">{{ $column }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Archivo CSV *</label>
                        <input type="file" name="customers_csv" accept=".csv,text/csv,text/plain" required class="block w-full text-xs font-semibold theme-text-heading file:mr-4 file:rounded-xl file:border-0 file:px-5 file:py-3 file:text-[10px] file:font-black file:uppercase file:tracking-widest theme-file-input">
                        @error('customers_csv')
                            <p class="text-[11px] font-bold text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="inline-flex items-center justify-center theme-button-dark px-6 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] shadow-lg theme-focus-primary">
                        Importar clientes
                    </button>
                </form>
            </div>

            <div class="theme-surface-dark rounded-[24px] p-6 shadow-xl shadow-slate-200">
                <p class="text-[10px] font-black uppercase tracking-[0.24em] theme-text-primary">Mapeo aplicado</p>
                <div class="mt-5 space-y-3 text-xs font-semibold text-slate-300">
                    <p><span class="font-black text-white">Nombre</span> pasa a name.</p>
                    <p><span class="font-black text-white">AP + AM</span> pasan a last_name.</p>
                    <p><span class="font-black text-white">Correo</span> pasa a email si es valido.</p>
                    <p><span class="font-black text-white">Telefono</span> pasa a phone solo con digitos.</p>
                    <p><span class="font-black text-white">estatus</span> 0 queda inactive; cualquier otro valor queda active.</p>
                    <p><span class="font-black text-white">ClienteID</span> se guarda en notes para evitar duplicados.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Importar servicios legacy</h3>
                    <p class="text-[11px] text-slate-400 font-medium mt-0.5">
                        Crea servicios en catalog_items y su precio vigente en price_histories para el tenant #{{ auth()->user()->tenant_id }}.
                    </p>
                </div>

                <form action="{{ route('client.mi-configuracion.import-services') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                    @csrf

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Columnas esperadas</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach(['ServID', 'ScType', 'Precio', 'estatus', 'created_at'] as $column)
                                <span class="rounded-lg bg-white border border-slate-200 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest theme-text-heading">{{ $column }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Archivo CSV *</label>
                        <input type="file" name="services_csv" accept=".csv,text/csv,text/plain" required class="block w-full text-xs font-semibold theme-text-heading file:mr-4 file:rounded-xl file:border-0 file:px-5 file:py-3 file:text-[10px] file:font-black file:uppercase file:tracking-widest theme-file-input">
                        @error('services_csv')
                            <p class="text-[11px] font-bold text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="inline-flex items-center justify-center theme-button-dark px-6 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] shadow-lg theme-focus-primary">
                        Importar servicios
                    </button>
                </form>
            </div>

            <div class="bg-white border border-slate-200 rounded-[24px] p-6 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.24em] theme-text-primary">Mapeo servicios</p>
                <div class="mt-5 space-y-3 text-xs font-semibold text-slate-500">
                    <p><span class="font-black theme-text-heading">ScType</span> pasa a catalog_items.name.</p>
                    <p><span class="font-black theme-text-heading">Precio</span> crea price_histories.price vigente.</p>
                    <p><span class="font-black theme-text-heading">estatus</span> 0 queda inactivo; cualquier otro valor queda activo.</p>
                    <p><span class="font-black theme-text-heading">ServID</span> se guarda en description para evitar duplicados.</p>
                    <p><span class="font-black theme-text-heading">type</span> siempre queda como service.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Importar caballos legacy</h3>
                    <p class="text-[11px] text-slate-400 font-medium mt-0.5">
                        Crea pacientes en animals usando el tipo Caballo/Caballos del tenant y relaciona ClienteID contra los clientes ya importados.
                    </p>
                </div>

                <form action="{{ route('client.mi-configuracion.import-horses') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                    @csrf

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Columnas esperadas</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach(['CaballoID', 'ClienteID', 'Nombre', 'FNacimiento', 'Color', 'Sexo', 'Raza', 'ClubID', 'Microchip', 'estatus', 'fechaNac', 'fotoChip', 'fechaRegistro'] as $column)
                                <span class="rounded-lg bg-white border border-slate-200 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest theme-text-heading">{{ $column }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Archivo CSV *</label>
                        <input type="file" name="horses_csv" accept=".csv,text/csv,text/plain" required class="block w-full text-xs font-semibold theme-text-heading file:mr-4 file:rounded-xl file:border-0 file:px-5 file:py-3 file:text-[10px] file:font-black file:uppercase file:tracking-widest theme-file-input">
                        @error('horses_csv')
                            <p class="text-[11px] font-bold text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="inline-flex items-center justify-center theme-button-dark px-6 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] shadow-lg theme-focus-primary">
                        Importar caballos
                    </button>
                </form>
            </div>

            <div class="theme-surface-dark rounded-[24px] p-6 shadow-xl shadow-slate-200">
                <p class="text-[10px] font-black uppercase tracking-[0.24em] theme-text-primary">Mapeo caballos</p>
                <div class="mt-5 space-y-3 text-xs font-semibold text-slate-300">
                    <p><span class="font-black text-white">ClienteID</span> busca customers.notes con Legacy ClienteID.</p>
                    <p><span class="font-black text-white">Nombre</span> pasa a animals.name.</p>
                    <p><span class="font-black text-white">fechaNac/FNacimiento</span> pasa a birthdate.</p>
                    <p><span class="font-black text-white">Sexo</span> se normaliza a male, female o unknown.</p>
                    <p><span class="font-black text-white">Color y Microchip</span> pasan directo a animals.</p>
                    <p><span class="font-black text-white">Raza, ClubID y fotoChip</span> quedan en notes.</p>
                </div>
            </div>
        </div>
    </div>
{{-- TAB: FACTURACIÓN --}}
<div x-show="currentTab === 'facturar'"
     x-transition:enter="transition duration-200"
     class="space-y-6">

    <form method="POST"
          action="{{ route('client.mi-configuracion.facturacion.store') }}"
          enctype="multipart/form-data">

        @csrf

        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">

            {{-- Header --}}
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">
                    Configuración Fiscal
                </h3>

                <p class="text-[11px] text-slate-400 font-medium mt-0.5">
                    Configura la información necesaria para emitir CFDI mediante Facturapi.
                </p>
            </div>

            {{-- Formulario --}}
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- Razón Social --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                        Razón Social
                    </label>

                    <input type="text"
                           name="legal_name"
                           value="{{ old('legal_name', $billingProfile?->legal_name) }}"
                           class="w-full rounded-xl border-slate-200 theme-input"
                           required>
                </div>

                {{-- RFC --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                        RFC
                    </label>

                    <input type="text"
                           name="tax_id"
                           value="{{ old('tax_id', $billingProfile?->tax_id) }}"
                           maxlength="13"
                           class="w-full rounded-xl border-slate-200 uppercase theme-input"
                           required>
                </div>

                {{-- Régimen Fiscal --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                        Régimen Fiscal
                    </label>

                    <select name="tax_system"
                            class="w-full rounded-xl border-slate-200 theme-input"
                            required>

                        <option value="">Seleccionar...</option>

                        <option value="601" @selected(old('tax_system', $billingProfile?->tax_system) == '601')>
                            601 - General de Ley Personas Morales
                        </option>

                        <option value="612" @selected(old('tax_system', $billingProfile?->tax_system) == '612')>
                            612 - Personas Físicas con Actividades Empresariales
                        </option>

                        <option value="626" @selected(old('tax_system', $billingProfile?->tax_system) == '626')>
                            626 - Régimen Simplificado de Confianza
                        </option>

                    </select>
                </div>

                {{-- Código Postal --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                        Código Postal Fiscal
                    </label>

                    <input type="text"
                           name="zip"
                           value="{{ old('zip', $billingProfile?->zip) }}"
                           maxlength="5"
                           class="w-full rounded-xl border-slate-200 theme-input"
                           required>
                </div>

                {{-- Correo --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                        Correo Fiscal
                    </label>

                    <input type="email"
                           name="email"
                           value="{{ old('email', $billingProfile?->email) }}"
                           class="w-full rounded-xl border-slate-200 theme-input">
                </div>

                {{-- API KEY --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                        API Key Facturapi
                    </label>

                    <input type="password"
                           name="facturapi_api_key"
                           class="w-full rounded-xl border-slate-200 theme-input">

                    <p class="mt-1 text-[10px] text-slate-400">
                        Se almacenará de forma segura.
                    </p>
                </div>

            </div>

        </div>

        {{-- CSD --}}
        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">

            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">
                    Certificados Digitales (CSD)
                </h3>

                <p class="text-[11px] text-slate-400 font-medium mt-0.5">
                    Archivos emitidos por el SAT para timbrar CFDI.
                </p>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">

                {{-- CER --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                        Archivo .CER
                    </label>

                    <input type="file"
                           name="csd_cer"
                           accept=".cer"
                           class="w-full text-sm">
                </div>

                {{-- KEY --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                        Archivo .KEY
                    </label>

                    <input type="file"
                           name="csd_key"
                           accept=".key"
                           class="w-full text-sm">
                </div>

                {{-- PASSWORD --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                        Contraseña CSD
                    </label>

                    <input type="password"
                           name="csd_password"
                           class="w-full rounded-xl border-slate-200 theme-input">
                </div>

            </div>
        </div>

        {{-- ESTADO --}}
        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm p-6">

            <div class="flex items-center justify-between">

                <div>
                    <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">
                        Estado del Servicio
                    </h3>

                    <p class="text-[11px] text-slate-400 mt-1">
                        Verifica que toda la configuración esté completa.
                    </p>
                </div>

                @if($billingProfile?->is_active)
                    <span class="inline-flex text-[10px] font-black uppercase tracking-widest text-emerald-700 bg-emerald-50 px-3 py-2 rounded-full">
                        🟢 Configurado
                    </span>
                @else
                    <span class="inline-flex text-[10px] font-black uppercase tracking-widest text-amber-700 bg-amber-50 px-3 py-2 rounded-full">
                        🟡 Pendiente
                    </span>
                @endif

            </div>

        </div>

        {{-- BOTÓN --}}
        <div class="flex justify-end">
            <button type="submit"
                    class="theme-button-dark px-6 py-3 rounded-xl font-bold text-xs tracking-wide shadow-sm transition-all theme-focus-primary">
                Guardar Configuración Fiscal
            </button>
        </div>

    </form>

</div>
    {{-- MODAL INTERNO: NUEVO ANIMAL TYPE --}}
    <div x-show="typeModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-data="{ loading: false }" x-transition>
        <div class="flex items-center justify-center min-h-screen px-4 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity theme-overlay backdrop-blur-sm" @click="if(!loading) typeModal = false"></div>
            
            <div class="inline-block overflow-hidden text-left align-middle transition-all transform bg-white rounded-[24px] shadow-2xl sm:my-8 sm:max-w-md sm:w-full border border-slate-100 relative" x-show="typeModal" x-transition>
                
                <div x-show="loading" class="absolute inset-0 bg-white/80 backdrop-blur-md z-50 flex flex-col items-center justify-center gap-4" style="display: none;">
                    <div class="w-10 h-10 border-4 border-slate-200 theme-border-top-primary rounded-full animate-spin"></div>
                    <p class="text-[10px] font-black theme-text-heading uppercase tracking-[0.2em]">Guardando Categoría...</p>
                </div>

                <form action="{{ route('client.mi-configuracion.store') }}" method="POST" @submit="loading = true">
                    @csrf
                    <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h3 class="text-lg font-black theme-text-heading tracking-tighter">Nuevo Tipo de Animal</h3>
                        <button type="button" @click="typeModal = false" :disabled="loading" class="text-slate-400 hover:text-red-500">✕</button>
                    </div>

                    <div class="p-8 space-y-4">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Nombre de la Especie *</label>
                            <input type="text" name="name" required placeholder="Ej. Canino, Felino, Reptiles" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading outline-none focus:ring-4 transition-all theme-input">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Descripción Breve</label>
                            <textarea name="description" rows="3" placeholder="Opcional: Detalles sobre variaciones o especificaciones del tipo de atención..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading outline-none transition-all resize-none theme-input"></textarea>
                        </div>
                    </div>

                    <div class="px-8 py-6 bg-slate-50 flex items-center justify-end gap-3 border-t border-slate-100">
                        <button type="button" @click="typeModal = false" :disabled="loading" class="text-xs font-black uppercase tracking-widest text-slate-400">Cancelar</button>
                        <button type="submit" :disabled="loading" class="theme-button-dark px-6 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] shadow-lg theme-focus-primary">Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
