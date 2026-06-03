@extends('layouts.admin')

@section('title', $tenant->name)

@section('content')

{{-- Inicializamos Alpine en el contenedor principal --}}
<div x-data="{ userModal: false }" class="space-y-8">

    @if(session('activation_code'))
        <div class="bg-white border border-[#38B2AC]/30 rounded-[24px] shadow-sm overflow-hidden">
            <div class="p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-[#38B2AC]">
                        Codigo de activacion generado
                    </p>
                    <h2 class="text-3xl font-black text-[#0F172A] tracking-widest mt-2">
                        {{ session('activation_code') }}
                    </h2>
                    <p class="text-sm text-slate-500 font-semibold mt-2">
                        Usuario: {{ session('activation_email') }} · Expira: {{ session('activation_expires_at') }}
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('activation.show') }}"
                       class="inline-flex items-center justify-center px-5 py-3 rounded-xl bg-[#0F172A] text-white text-[10px] font-black uppercase tracking-widest hover:bg-slate-800 transition">
                        Ir a activacion
                    </a>
                    <button type="button"
                            x-data
                            @click="navigator.clipboard.writeText('{{ session('activation_code') }}')"
                            class="inline-flex items-center justify-center px-5 py-3 rounded-xl border border-slate-200 text-[#0F172A] text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition">
                        Copiar codigo
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Header Compacto --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-6">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-[#0F172A] flex items-center justify-center text-2xl shadow-xl">
                <span class="text-white font-black">{{ substr($tenant->name, 0, 1) }}</span>
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-black text-[#0F172A] tracking-tighter">{{ $tenant->name }}</h1>
                    <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-[10px] font-black uppercase tracking-widest {{ $tenant->status === 'active' ? 'bg-[#38B2AC]/10 text-[#38B2AC]' : 'bg-slate-100 text-slate-500' }}">
                        {{ $tenant->status }}
                    </span>
                </div>
                <p class="text-slate-500 font-medium text-sm">{{ $tenant->business_name ?: 'Sin razón social registrada' }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <form method="POST" action="{{ route('admin.tenants.resend-activation-code', $tenant) }}">
                @csrf
                <button type="submit"
                        class="px-6 py-3 rounded-xl bg-[#0F172A] text-white text-xs font-black uppercase tracking-widest hover:bg-slate-800 transition-all">
                    Generar Acceso
                </button>
            </form>
            <a href="{{ route('admin.tenants.edit', $tenant) }}" 
               class="px-6 py-3 rounded-xl border-2 border-slate-200 text-xs font-black text-[#0F172A] uppercase tracking-widest hover:bg-slate-50 transition-all">
                Configurar Cuenta
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 font-medium">
        
        {{-- COLUMNA IZQUIERDA: Info y KPIs --}}
        <div class="lg:col-span-4 space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Plan Actual</p>
                    <p class="text-lg font-black text-[#0F172A] mt-1">{{ $tenant->plan->name ?? 'N/A' }}</p>
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Usuarios</p>
                    <p class="text-lg font-black text-[#0F172A] mt-1">{{ $tenant->users->count() }}</p>
                </div>
            </div>

            <div class="bg-slate-900 rounded-[24px] p-6 text-white shadow-xl relative overflow-hidden">
                <div class="relative z-10 space-y-4">
                    <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-[#38B2AC]">Datos de Contacto</h3>
                    <div class="space-y-1">
                        <p class="text-[10px] text-white/40 uppercase font-black">Email Corporativo</p>
                        <p class="text-sm truncate">{{ $tenant->email ?: 'N/A' }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] text-white/40 uppercase font-black">Línea Directa</p>
                        <p class="text-sm">{{ $tenant->phone ?: 'N/A' }}</p>
                    </div>
                    <div class="pt-4 border-t border-white/10 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] text-white/40 uppercase font-black">Suscripción</span>
                            <span class="text-xs font-bold">{{ $tenant->subscription_ends_at ? $tenant->subscription_ends_at->format('d M, Y') : 'Vencida' }}</span>
                        </div>
                    </div>
                </div>
                <div class="absolute -right-4 -bottom-4 text-7xl opacity-10">🏥</div>
            </div>
        </div>

        {{-- COLUMNA DERECHA: Tablas --}}
        <div class="lg:col-span-8 space-y-8">
            
            {{-- TABLA: Historial de Suscripciones --}}
          <div 
    x-data="{ openAssignPlanModal: false }"
    class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden"
>
    <div class="px-8 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between gap-4">
        <h2 class="font-black text-[11px] uppercase tracking-widest text-slate-500 underline decoration-[#38B2AC] decoration-2 underline-offset-4">
            Historial de Pagos
        </h2>

        <button 
            type="button"
            @click="openAssignPlanModal = true"
            class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-[#38B2AC] text-white text-[11px] font-black uppercase tracking-widest hover:bg-[#2C9A94] transition"
        >
            Agregar Plan
        </button>
    </div>

    @if($pendingPlanRequest)
        <div class="mx-8 mt-6 rounded-2xl border border-amber-100 bg-amber-50 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-widest text-amber-700">Solicitud de cambio pendiente</p>
                <p class="text-sm font-black text-amber-950 mt-1">
                    El cliente solicito cambiar a {{ $pendingPlanRequest->plan?->name ?? 'otro plan' }}.
                </p>
                <p class="text-[11px] font-semibold text-amber-700 mt-1">
                    Fecha: {{ $pendingPlanRequest->created_at->format('d/m/Y H:i') }}
                </p>
            </div>
            <button
                type="button"
                @click="openAssignPlanModal = true"
                class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-amber-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-amber-700 transition"
            >
                Atender solicitud
            </button>
        </div>
    @endif
 
    <table class="w-full text-left">
    
       <thead>
    <tr class="text-[10px] font-black uppercase tracking-widest text-slate-400 border-b border-slate-100">
        <th class="px-8 py-4">Fecha</th>
        <th class="px-8 py-4">Plan</th>
        <th class="px-8 py-4">Monto</th>
        <th class="px-8 py-4">Método</th>
        <th class="px-8 py-4">Status</th>
        <th class="px-8 py-4">Vence</th>
    </tr>
</thead>

<tbody class="divide-y divide-slate-50 text-sm">
    @forelse($payments as $payment)
        <tr class="hover:bg-slate-50 transition">
            <td class="px-8 py-4 font-bold text-slate-600">
                {{ $payment->paid_at?->format('d M, Y') ?? '—' }}
            </td>

            <td class="px-8 py-4 font-bold text-slate-800">
                {{ $payment->plan?->name ?? 'Sin plan' }}
            </td>

            <td class="px-8 py-4 font-black text-slate-900">
                ${{ number_format($payment->amount, 2) }}
                <span class="text-[10px] text-slate-400">{{ $payment->currency }}</span>
            </td>

            <td class="px-8 py-4">
                <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-widest">
                    {{ $payment->payment_method ?? $payment->provider ?? 'manual' }}
                </span>
            </td>

            <td class="px-8 py-4">
                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest
                    @if($payment->status === 'paid')
                        bg-emerald-50 text-emerald-600
                    @elseif($payment->status === 'pending')
                        bg-yellow-50 text-yellow-600
                    @elseif($payment->status === 'failed')
                        bg-red-50 text-red-600
                    @else
                        bg-slate-100 text-slate-500
                    @endif
                ">
                    {{ $payment->status }}
                </span>
            </td>

            <td class="px-8 py-4 font-bold text-slate-600">
                {{ $payment->period_ends_at?->format('d M, Y') ?? '—' }}
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="px-8 py-6 text-center text-slate-400 font-bold uppercase text-[10px] tracking-widest italic">
                Sin pagos registrados
            </td>
        </tr>
    @endforelse
</tbody>
    </table>

    {{-- MODAL ASIGNAR PLAN --}}
    <div
        x-show="openAssignPlanModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
    >
        <div 
            class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
            @click="openAssignPlanModal = false"
        ></div>

        <div class="relative bg-white w-full max-w-2xl rounded-[28px] shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-8 py-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-black text-slate-900">
                        Asignar nuevo plan
                    </h3>
                    <p class="text-xs text-slate-500 font-semibold">
                        Cliente: {{ $tenant->business_name ?? $tenant->name }}
                    </p>
                </div>

                <button 
                    type="button"
                    @click="openAssignPlanModal = false"
                    class="w-9 h-9 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 font-black"
                >
                    ×
                </button>
            </div>

            <form method="POST" action="{{ route('admin.tenants.assign-plan', $tenant) }}" class="p-8 space-y-5">
                @csrf

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                        Plan
                    </label>
                    <select 
                        name="plan_id"
                        required
                        class="w-full rounded-2xl border-slate-200 focus:border-[#38B2AC] focus:ring-[#38B2AC]"
                    >
                        <option value="">Selecciona un plan</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}">
                                {{ $plan->name }} - ${{ number_format($plan->price ?? 0, 2) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                            Fecha inicio
                        </label>
                        <input 
                            type="date"
                            name="starts_at"
                            value="{{ now()->format('Y-m-d') }}"
                            required
                            class="w-full rounded-2xl border-slate-200 focus:border-[#38B2AC] focus:ring-[#38B2AC]"
                        >
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                            Fecha vencimiento
                        </label>
                        <input 
                            type="date"
                            name="ends_at"
                            value="{{ now()->addMonth()->format('Y-m-d') }}"
                            required
                            class="w-full rounded-2xl border-slate-200 focus:border-[#38B2AC] focus:ring-[#38B2AC]"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                            Monto
                        </label>
                        <input 
                            type="number"
                            step="0.01"
                            name="amount"
                            value="0.00"
                            required
                            class="w-full rounded-2xl border-slate-200 focus:border-[#38B2AC] focus:ring-[#38B2AC]"
                        >
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                            Método de pago
                        </label>
                        <select 
                            name="payment_method"
                            required
                            class="w-full rounded-2xl border-slate-200 focus:border-[#38B2AC] focus:ring-[#38B2AC]"
                        >
                            <option value="manual">Manual</option>
                            <option value="cash">Efectivo</option>
                            <option value="transfer">Transferencia</option>
                            <option value="card_manual">Tarjeta de credito manual</option>
                            <option value="card">Tarjeta</option>
                            <option value="stripe">Stripe</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                        Referencia de pago
                    </label>
                    <input 
                        type="text"
                        name="payment_reference"
                        placeholder="Referencia, folio, autorización, etc."
                        class="w-full rounded-2xl border-slate-200 focus:border-[#38B2AC] focus:ring-[#38B2AC]"
                    >
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                        Notas
                    </label>
                    <textarea 
                        name="notes"
                        rows="3"
                        class="w-full rounded-2xl border-slate-200 focus:border-[#38B2AC] focus:ring-[#38B2AC]"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4">
                    <button 
                        type="button"
                        @click="openAssignPlanModal = false"
                        class="px-5 py-3 rounded-2xl bg-slate-100 text-slate-600 text-[11px] font-black uppercase tracking-widest hover:bg-slate-200"
                    >
                        Cancelar
                    </button>

                    <button 
                        type="submit"
                        class="px-5 py-3 rounded-2xl bg-[#38B2AC] text-white text-[11px] font-black uppercase tracking-widest hover:bg-[#2C9A94]"
                    >
                        Guardar plan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

            {{-- TABLA: Usuarios --}}
            <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
                <div class="px-8 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <h2 class="font-black text-[11px] uppercase tracking-widest text-slate-500">
                        Equipo de Trabajo
                    </h2>
                    <button @click="userModal = true" class="text-[10px] font-black text-[#38B2AC] uppercase tracking-tighter hover:underline">
                        + Agregar Miembro
                    </button>
                </div>
                <table class="w-full text-left">
                    <tbody class="divide-y divide-slate-100 italic">
                        @forelse($tenant->users as $user)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-8 py-4">
                                    <p class="font-black text-[#0F172A] not-italic">{{ $user->name }}</p>
                                    <p class="text-xs text-slate-400 font-medium">{{ $user->email }}</p>
                                </td>
                                <td class="px-8 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($user->getRoleNames() as $role)
                                            <span class="px-2 py-0.5 rounded bg-slate-100 text-[9px] font-black uppercase text-slate-600 tracking-tighter not-italic">
                                                {{ $role }}
                                            </span>
                                        @endforeach
                                        @if(!$user->invitation_accepted_at)
                                            <span class="px-2 py-0.5 rounded {{ $user->invitation_expires_at?->isPast() ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700' }} text-[9px] font-black uppercase tracking-tighter not-italic">
                                                {{ $user->invitation_expires_at?->isPast() ? 'Codigo expirado' : 'Pendiente activar' }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-8 py-4">
                                    <div class="flex items-center justify-end gap-3">
                                        @if(!$user->invitation_accepted_at)
                                            <form method="POST" action="{{ route('admin.tenants.users.resend-activation-code', [$tenant, $user]) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="px-3 py-2 rounded-xl bg-[#0F172A] text-white text-[9px] font-black uppercase tracking-widest hover:bg-slate-800 transition-colors not-italic">
                                                    Reenviar codigo
                                                </button>
                                            </form>
                                        @endif
                                    <button class="text-slate-300 hover:text-red-500 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-8 py-10 text-center text-slate-400 text-xs font-bold uppercase tracking-widest">Sin usuarios activos</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

   {{-- MODAL: Nuevo Usuario --}}
<div x-show="userModal" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 overflow-y-auto" 
     style="display: none;"
     {{-- Estado local para controlar la pantalla de carga --}}
     x-data="{ loading: false }">
    
    <div class="flex items-center justify-center min-h-screen px-4 text-center sm:p-0">
        {{-- Overlay con Blur general --}}
        <div class="fixed inset-0 transition-opacity bg-[#0F172A]/80 backdrop-blur-sm" @click="if(!loading) userModal = false"></div>

        <div class="inline-block overflow-hidden text-left align-middle transition-all transform bg-white rounded-[24px] shadow-2xl sm:my-8 sm:max-w-lg sm:w-full border border-slate-100 relative"
             x-show="userModal"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0">
            
            {{-- PANTALLA DE CARGA (Aparece al procesar el formulario) --}}
            <div x-show="loading" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="absolute inset-0 bg-white/80 backdrop-blur-md z-50 flex flex-col items-center justify-center gap-4"
                 style="display: none;">
                {{-- Spinner Minimalista --}}
                <div class="w-10 h-10 border-4 border-slate-200 border-t-[#38B2AC] rounded-full animate-spin"></div>
                <p class="text-[10px] font-black text-[#0F172A] uppercase tracking-[0.2em] animate-pulse">Procesando Registro...</p>
            </div>

            {{-- Formulario --}}
            <form action="{{ route('admin.tenants.users.store', $tenant) }}"
                  method="POST"
                  @submit="loading = true" {{-- Activa la pantalla de carga al enviar --}}
                  class="space-y-0">
                @csrf
                
                {{-- Header --}}
                <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="text-lg font-black text-[#0F172A] tracking-tighter">Vincular Nuevo Miembro</h3>
                    <button type="button" @click="userModal = false" :disabled="loading" class="text-slate-400 hover:text-red-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                {{-- Contenido --}}
                <div class="p-8 space-y-5 font-medium text-slate-700">
                    {{-- Errores de Validación --}}
                    @if ($errors->any())
                        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                            <ul class="list-disc list-inside text-xs text-red-600 font-bold uppercase tracking-widest space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Input: Nombre --}}
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Nombre Completo</label>
                        <input type="text" 
                               name="name" 
                               value="{{ old('name') }}"
                               required 
                               placeholder="Ej. Dr. Carlos Gorozpe"
                               class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3.5 text-sm font-semibold text-[#0F172A] placeholder-slate-400 focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none shadow-sm">
                    </div>

                    {{-- Input: Email --}}
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Correo Electrónico</label>
                        <input type="email" 
                               name="email" 
                               value="{{ old('email') }}"
                               required 
                               placeholder="nombre@vetsys.com"
                               class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3.5 text-sm font-semibold text-[#0F172A] placeholder-slate-400 focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none shadow-sm">
                    </div>

                    {{-- Input: Rol --}}
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Asignar Rol en la Clínica</label>
                        <div class="relative">
                            <select name="role" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3.5 text-sm font-black text-[#0F172A] focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none shadow-sm appearance-none">
                                <option value="client-admin">Administrador (Acceso Total)</option>
                                <option value="client-user">Editor / Usuario Estándar</option>
                            </select>
                            {{-- Icono de flecha personalizado para el select --}}
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </div>
                    
                    <p class="text-[10px] text-slate-400 pt-4 border-t border-slate-100 font-medium italic">Se generara un codigo de activacion de 6 digitos para que el miembro configure su contrasena.</p>
                </div>

                {{-- Footer de Acciones --}}
                <div class="px-8 py-6 bg-slate-50 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" 
                            @click="userModal = false" 
                            :disabled="loading"
                            class="text-xs font-black uppercase tracking-widest text-slate-400 hover:text-slate-600 transition-colors disabled:opacity-50">
                        Cancelar
                    </button>
                    <button type="submit" 
                            :disabled="loading"
                            class="bg-[#0F172A] px-6 py-3.5 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg shadow-slate-200 transition-all disabled:opacity-50">
                        Registrar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</div>
{{-- Componente de Toast --}}
<div x-data="{ 
        show: false, 
        message: '', 
        type: 'success',
        init() {
            @if(session('success'))
                this.pop('{{ session('success') }}', 'success');
            @endif
            @if(session('error'))
                this.pop('{{ session('error') }}', 'error');
            @endif
        },
        pop(msg, type) {
            this.message = msg;
            this.type = type;
            this.show = true;
            setTimeout(() => { this.show = false }, 5000);
        }
    }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="translate-y-10 opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed bottom-10 right-10 z-[100]"
    style="display: none;">
    
    <div :class="type === 'success' ? 'bg-[#0F172A]' : 'bg-red-600'" 
         class="rounded-2xl px-6 py-4 shadow-2xl flex items-center gap-4 border border-white/10">
        <div class="w-2 h-2 rounded-full" :class="type === 'success' ? 'bg-[#38B2AC]' : 'bg-white'"></div>
        <p class="text-white text-[10px] font-black uppercase tracking-[0.2em]" x-text="message"></p>
        <button @click="show = false" class="text-white/40 hover:text-white ml-4">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
</div>
@endsection
