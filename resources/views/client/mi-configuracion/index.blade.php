@extends('layouts.client')

@section('title', 'Configuración General')

@section('content')
{{-- Centralizamos el estado de las pestañas y del modal de especies con Alpine --}}
<div class="space-y-8" x-data="{ currentTab: @js(session('activeTab', 'animales')), typeModal: false }">
    
    {{-- SISTEMA DE TOASTS FLOTANTES --}}
    <div class="fixed top-4 right-4 z-[99] space-y-3 min-w-[320px]">
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition class="bg-white border-l-4 border-emerald-500 rounded-xl shadow-xl p-4 flex items-center justify-between border border-slate-100">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold">✓</span>
                    <div>
                        <p class="text-xs font-black text-[#0F172A] uppercase tracking-wider">Configuración</p>
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
                        <p class="text-xs font-black text-[#0F172A] uppercase tracking-wider">Error de Ajuste</p>
                        <p class="text-[11px] text-slate-500 font-semibold mt-0.5">Por favor, revisa los datos del catálogo.</p>
                    </div>
                </div>
                <button @click="show = false" class="text-slate-400 hover:text-slate-600 text-xs ml-4">✕</button>
            </div>
        @endif
    </div>

    {{-- HEADER --}}
    <div>
        <h1 class="text-3xl font-black text-[#0F172A] tracking-tighter">Panel de Configuración</h1>
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Inicializa los catálogos primarios, usuarios y finanzas de tu clínica.</p>
    </div>

    {{-- TABS DE CONTROL --}}
    <div class="flex border-b border-slate-200 gap-2 overflow-x-auto">
        <button @click="currentTab = 'animales'" 
                :class="currentTab === 'animales' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            🐕 Tipos de Animales
        </button>
        <button @click="currentTab = 'usuarios'" 
                :class="currentTab === 'usuarios' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            👥 Usuarios / Equipo
        </button>
        <button @click="currentTab = 'bancos'" 
                :class="currentTab === 'bancos' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            💳 Cuentas Bancarias
        </button>
        {{-- Nuevo Tab para Métodos de Pago --}}
        <button @click="currentTab = 'pagos'" 
                :class="currentTab === 'pagos' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            💰 Métodos de Pago
        </button>
        <button @click="currentTab = 'facturacion'"
                :class="currentTab === 'facturacion' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            🧾 Plan y Pagos
        </button>
         <button @click="currentTab = 'roles'"
                :class="currentTab === 'roles' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            🧾 Roles
        </button>
        <button @click="currentTab = 'importar'"
                :class="currentTab === 'importar' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                class="border-b-2 px-4 py-3 text-xs font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap">
            ⬆️ Importar Catalogos
        </button>
    </div>

    {{-- CONTENIDO DE LAS PESTAÑAS --}}
    
    {{-- TAB 1: TIPOS DE ANIMALES --}}
    <div x-show="currentTab === 'animales'" x-transition:enter="transition duration-200" class="space-y-6">
        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div>
                    <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Especies Clínicas Habilitadas</h3>
                    <p class="text-[11px] text-slate-400 font-medium mt-0.5">Registra los tipos de animales que tu personal puede atender en las fichas médicas.</p>
                </div>
                <button @click="typeModal = true" class="bg-[#0F172A] hover:bg-slate-800 text-white px-4 py-2.5 rounded-xl font-bold text-xs tracking-wide shadow-sm transition-all">
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
                                        <div class="w-8 h-8 rounded-lg bg-teal-50 text-[#38B2AC] flex items-center justify-center font-black text-xs">
                                            🐾
                                        </div>
                                        <span class="text-xs font-bold text-[#0F172A]">{{ $type->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-xs font-mono text-slate-400">{{ $type->slug }}</td>
                                <td class="px-6 py-4 text-xs text-slate-500 max-w-xs truncate">{{ $type->description ?? 'Sin descripción añadida.' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest {{ $type->is_active ? 'text-emerald-700 bg-emerald-50' : 'text-slate-400 bg-slate-100' }} px-2.5 py-1 rounded-full">
                                        {{ $type->is_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <!-- <button class="p-1.5 text-slate-400 hover:text-[#0F172A] transition-colors" title="Editar Campos Dinámicos">⚙️ Campos</button> -->
                                    <a href="{{ route('client.mi-configuracion.fields.index', $type->id) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 border border-slate-200 hover:border-slate-300 rounded-lg text-xs font-semibold text-[#0F172A] transition-colors shadow-sm">
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
                        <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Usuarios del equipo</h3>
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
                                            <div class="w-9 h-9 rounded-xl bg-[#38B2AC]/10 text-[#38B2AC] flex items-center justify-center font-black text-xs">
                                                {{ strtoupper(substr($teamUser->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <p class="text-xs font-black text-[#0F172A]">{{ $teamUser->name }}</p>
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
                            <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Invitar usuario</h3>
                            <p class="text-[11px] text-slate-400 font-medium mt-1">Se enviara un correo para que configure su contrasena.</p>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Nombre *</label>
                            <input type="text" name="name" value="{{ old('name') }}" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] outline-none focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Correo *</label>
                            <input type="email" name="email" value="{{ old('email') }}" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] outline-none focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Rol *</label>
                            <select name="role" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] outline-none focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all">
                                @foreach($roleOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="w-full bg-[#0F172A] px-6 py-3.5 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg">
                            Enviar invitacion
                        </button>
                    </form>
                @elseif(!$canManageTeam)
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center text-2xl mx-auto mb-4">!</div>
                        <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Sin permisos</h3>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto mt-2">
                            Solo un Administrador puede invitar usuarios y asignar roles dentro del tenant.
                        </p>
                    </div>
                @else
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center text-2xl mx-auto mb-4">+</div>
                        <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Mejora tu plan</h3>
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
        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm p-8 text-center">
            <div class="w-16 h-16 bg-purple-50 text-purple-500 rounded-full flex items-center justify-center text-2xl mx-auto mb-4">💳</div>
            <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Cuentas e Ingresos</h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto mt-1">Próximamente configurarás tus cuentas de banco para vincularlas a los pagos de las consultas y venta de productos.</p>
        </div>
    </div>
    @include('client.mi-configuracion.payment-methods.index')

    {{-- TAB 5: PLAN Y PAGOS --}}
    <div x-show="currentTab === 'facturacion'" x-transition:enter="transition duration-200" class="space-y-6" style="display: none;">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 bg-[#0F172A] rounded-[24px] p-6 shadow-xl shadow-slate-200 text-white overflow-hidden relative">
                <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full bg-[#38B2AC]/30"></div>
                <div class="relative">
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-[#38B2AC]">Plan actual</p>
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
                    <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Contratar otro plan</h3>
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
                                    <h4 class="text-base font-black text-[#0F172A]">{{ $plan->name }}</h4>
                                    <p class="text-[11px] text-slate-400 font-semibold mt-1 line-clamp-2">{{ $plan->description ?? 'Sin descripcion.' }}</p>
                                </div>
                                @if($tenant?->plan_id === $plan->id)
                                    <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-widest text-emerald-700">Actual</span>
                                @endif
                            </div>

                            <p class="text-2xl font-black text-[#0F172A] mt-4">${{ number_format($plan->price, 2) }} <span class="text-[10px] text-slate-400 uppercase">{{ $plan->currency }}</span></p>
                            <div class="mt-4 flex flex-wrap gap-2 text-[10px] font-black uppercase tracking-widest text-slate-500">
                                <span class="rounded-lg bg-slate-100 px-2.5 py-1">{{ $plan->billing_period }}</span>
                                <span class="rounded-lg bg-slate-100 px-2.5 py-1">{{ $plan->max_users ?? 'Sin limite' }} usuarios</span>
                                <span class="rounded-lg bg-slate-100 px-2.5 py-1">{{ $plan->max_clients ?? 'Sin limite' }} clientes</span>
                            </div>

                            <form action="{{ route('client.mi-configuracion.plan.request') }}" method="POST" class="mt-5 space-y-3">
                                @csrf
                                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                <div class="grid grid-cols-1 gap-2">
                                    <select name="payment_method" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-[#0F172A] focus:outline-none focus:border-[#38B2AC]">
                                        <option value="card_manual">Tarjeta de credito manual</option>
                                        <option value="transfer">Transferencia</option>
                                        <option value="cash">Efectivo</option>
                                        <option value="other">Otro</option>
                                    </select>
                                    <input type="text" name="payment_reference" placeholder="Referencia opcional, no escribas datos de tarjeta" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-semibold text-[#0F172A] focus:outline-none focus:border-[#38B2AC]">
                                </div>
                                <button type="submit"
                                        class="w-full px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-[#0F172A] text-white hover:bg-slate-800 shadow-sm">
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
                <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Historial de pagos de suscripcion</h3>
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
                                <td class="px-6 py-4 text-xs font-bold text-[#0F172A]">{{ $payment->plan?->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 text-xs font-semibold text-slate-500">{{ $payment->payment_method ?? ucfirst($payment->provider) }}</td>
                                <td class="px-6 py-4 text-xs font-semibold text-slate-400">{{ $payment->payment_reference ?? $payment->provider_invoice_id ?? '--' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest {{ $payment->status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $payment->status }}</span>
                                </td>
                                <td class="px-6 py-4 text-xs font-black text-right text-[#0F172A]">${{ number_format($payment->amount, 2) }} {{ $payment->currency }}</td>
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
                    <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Roles del tenant</h3>
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
                                    <td class="px-6 py-4 text-right text-xs font-black text-[#0F172A]">
                                        {{ $teamUsers->filter(fn ($teamUser) => $teamUser->roles->pluck('name')->contains($roleValue))->count() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-[#0F172A] rounded-[24px] p-6 text-white shadow-xl shadow-slate-200">
                <p class="text-[10px] font-black uppercase tracking-[0.24em] text-[#38B2AC]">Regla SaaS</p>
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
                    <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Importar clientes legacy</h3>
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
                                <span class="rounded-lg bg-white border border-slate-200 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-[#0F172A]">{{ $column }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Archivo CSV *</label>
                        <input type="file" name="customers_csv" accept=".csv,text/csv,text/plain" required class="block w-full text-xs font-semibold text-[#0F172A] file:mr-4 file:rounded-xl file:border-0 file:bg-[#0F172A] file:px-5 file:py-3 file:text-[10px] file:font-black file:uppercase file:tracking-widest file:text-white hover:file:bg-slate-800">
                        @error('customers_csv')
                            <p class="text-[11px] font-bold text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="inline-flex items-center justify-center bg-[#0F172A] px-6 py-3.5 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg">
                        Importar clientes
                    </button>
                </form>
            </div>

            <div class="bg-[#0F172A] rounded-[24px] p-6 text-white shadow-xl shadow-slate-200">
                <p class="text-[10px] font-black uppercase tracking-[0.24em] text-[#38B2AC]">Mapeo aplicado</p>
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
                    <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Importar servicios legacy</h3>
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
                                <span class="rounded-lg bg-white border border-slate-200 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-[#0F172A]">{{ $column }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Archivo CSV *</label>
                        <input type="file" name="services_csv" accept=".csv,text/csv,text/plain" required class="block w-full text-xs font-semibold text-[#0F172A] file:mr-4 file:rounded-xl file:border-0 file:bg-[#0F172A] file:px-5 file:py-3 file:text-[10px] file:font-black file:uppercase file:tracking-widest file:text-white hover:file:bg-slate-800">
                        @error('services_csv')
                            <p class="text-[11px] font-bold text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="inline-flex items-center justify-center bg-[#0F172A] px-6 py-3.5 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg">
                        Importar servicios
                    </button>
                </form>
            </div>

            <div class="bg-white border border-slate-200 rounded-[24px] p-6 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.24em] text-[#38B2AC]">Mapeo servicios</p>
                <div class="mt-5 space-y-3 text-xs font-semibold text-slate-500">
                    <p><span class="font-black text-[#0F172A]">ScType</span> pasa a catalog_items.name.</p>
                    <p><span class="font-black text-[#0F172A]">Precio</span> crea price_histories.price vigente.</p>
                    <p><span class="font-black text-[#0F172A]">estatus</span> 0 queda inactivo; cualquier otro valor queda activo.</p>
                    <p><span class="font-black text-[#0F172A]">ServID</span> se guarda en description para evitar duplicados.</p>
                    <p><span class="font-black text-[#0F172A]">type</span> siempre queda como service.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Importar caballos legacy</h3>
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
                                <span class="rounded-lg bg-white border border-slate-200 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-[#0F172A]">{{ $column }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Archivo CSV *</label>
                        <input type="file" name="horses_csv" accept=".csv,text/csv,text/plain" required class="block w-full text-xs font-semibold text-[#0F172A] file:mr-4 file:rounded-xl file:border-0 file:bg-[#0F172A] file:px-5 file:py-3 file:text-[10px] file:font-black file:uppercase file:tracking-widest file:text-white hover:file:bg-slate-800">
                        @error('horses_csv')
                            <p class="text-[11px] font-bold text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="inline-flex items-center justify-center bg-[#0F172A] px-6 py-3.5 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg">
                        Importar caballos
                    </button>
                </form>
            </div>

            <div class="bg-[#0F172A] rounded-[24px] p-6 text-white shadow-xl shadow-slate-200">
                <p class="text-[10px] font-black uppercase tracking-[0.24em] text-[#38B2AC]">Mapeo caballos</p>
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

    {{-- MODAL INTERNO: NUEVO ANIMAL TYPE --}}
    <div x-show="typeModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-data="{ loading: false }" x-transition>
        <div class="flex items-center justify-center min-h-screen px-4 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-[#0F172A]/80 backdrop-blur-sm" @click="if(!loading) typeModal = false"></div>
            
            <div class="inline-block overflow-hidden text-left align-middle transition-all transform bg-white rounded-[24px] shadow-2xl sm:my-8 sm:max-w-md sm:w-full border border-slate-100 relative" x-show="typeModal" x-transition>
                
                <div x-show="loading" class="absolute inset-0 bg-white/80 backdrop-blur-md z-50 flex flex-col items-center justify-center gap-4" style="display: none;">
                    <div class="w-10 h-10 border-4 border-slate-200 border-t-[#38B2AC] rounded-full animate-spin"></div>
                    <p class="text-[10px] font-black text-[#0F172A] uppercase tracking-[0.2em]">Guardando Categoría...</p>
                </div>

                <form action="{{ route('client.mi-configuracion.store') }}" method="POST" @submit="loading = true">
                    @csrf
                    <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h3 class="text-lg font-black text-[#0F172A] tracking-tighter">Nuevo Tipo de Animal</h3>
                        <button type="button" @click="typeModal = false" :disabled="loading" class="text-slate-400 hover:text-red-500">✕</button>
                    </div>

                    <div class="p-8 space-y-4">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Nombre de la Especie *</label>
                            <input type="text" name="name" required placeholder="Ej. Canino, Felino, Reptiles" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] outline-none focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Descripción Breve</label>
                            <textarea name="description" rows="3" placeholder="Opcional: Detalles sobre variaciones o especificaciones del tipo de atención..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] outline-none focus:border-[#38B2AC] transition-all resize-none"></textarea>
                        </div>
                    </div>

                    <div class="px-8 py-6 bg-slate-50 flex items-center justify-end gap-3 border-t border-slate-100">
                        <button type="button" @click="typeModal = false" :disabled="loading" class="text-xs font-black uppercase tracking-widest text-slate-400">Cancelar</button>
                        <button type="submit" :disabled="loading" class="bg-[#0F172A] px-6 py-3.5 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg">Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
