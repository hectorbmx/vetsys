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
                    @if(session('activation_link'))
                        <p class="text-xs text-slate-500 font-semibold mt-3 break-all">
                            Link: {{ session('activation_link') }}
                        </p>
                    @endif
                    @if(session('activation_mail_sent'))
                        <p class="text-xs font-black text-emerald-600 uppercase tracking-widest mt-3">
                            {{ session('activation_mail_sent') }}
                        </p>
                    @endif
                    @if(session('activation_mail_failed'))
                        <p class="text-xs font-black text-amber-600 uppercase tracking-widest mt-3">
                            {{ session('activation_mail_failed') }}
                        </p>
                    @endif
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
                    @if(session('activation_link'))
                        <button type="button"
                                x-data
                                @click="navigator.clipboard.writeText('{{ session('activation_link') }}')"
                                class="inline-flex items-center justify-center px-5 py-3 rounded-xl border border-slate-200 text-[#0F172A] text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition">
                            Copiar link
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

 @if(session('stripe_checkout_link'))
    <div class="bg-white border border-[#25D366]/30 rounded-[24px] shadow-sm overflow-hidden">
        <div class="p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-5">
            <div class="flex items-start gap-4">
                {{-- Ícono WhatsApp --}}
                <div class="shrink-0 w-11 h-11 rounded-2xl bg-[#25D366] flex items-center justify-center shadow">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" class="w-6 h-6">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                        <path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.118 1.528 5.847L.057 23.882a.5.5 0 0 0 .606.63l6.204-1.63A11.94 11.94 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.894a9.877 9.877 0 0 1-5.031-1.378l-.36-.214-3.733.981.995-3.641-.235-.374A9.865 9.865 0 0 1 2.106 12C2.106 6.533 6.533 2.106 12 2.106S21.894 6.533 21.894 12 17.467 21.894 12 21.894z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-[#25D366]">
                        Link de pago listo para enviar
                    </p>
                    <p class="text-xs text-slate-500 font-semibold mt-1 break-all">
                        {{ session('stripe_checkout_link') }}
                    </p>
                    <p class="text-[10px] text-slate-400 font-semibold mt-1">
                        Copia el link y envíalo al cliente por WhatsApp
                    </p>
                </div>
            </div>
            <button type="button"
                    x-data="{ copied: false }"
                    @click="navigator.clipboard.writeText('{{ session('stripe_checkout_link') }}'); copied = true; setTimeout(() => copied = false, 2500)"
                    class="shrink-0 inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-white text-[10px] font-black uppercase tracking-widest transition"
                    :class="copied ? 'bg-emerald-500' : 'bg-[#25D366] hover:bg-[#1ebe5d]'">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path x-show="!copied" stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    <path x-show="copied" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <span x-text="copied ? '¡Copiado!' : 'Copiar link'"></span>
            </button>
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

   <div class="space-y-6 font-medium">

    {{-- ====== FRANJA DE RESUMEN ====== --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Plan + Usuarios --}}
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm grid grid-cols-2 gap-4 content-center">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Plan asignado</p>
                <p class="text-lg font-black text-[#0F172A] mt-1">{{ $tenant->plan->name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Usuarios</p>
                <p class="text-lg font-black text-[#0F172A] mt-1">{{ $tenant->users->count() }}</p>
            </div>
        </div>

        {{-- Suscripción --}}
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Suscripcion</p>
                    <p class="text-lg font-black text-[#0F172A] mt-1">{{ $billingSummary['label'] }}</p>
                    <p class="text-[11px] font-semibold text-slate-500 mt-0.5">{{ $billingSummary['description'] }}</p>
                </div>
                <span class="shrink-0 inline-flex rounded-lg px-3 py-1.5 text-[10px] font-black uppercase tracking-widest {{ $billingSummary['badge'] }}">
                    {{ $billingSummary['status'] }}
                </span>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-3 border-t border-slate-100 pt-3">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Vence</p>
                    <p class="text-sm font-black text-[#0F172A] mt-0.5">{{ $billingSummary['ends_at'] ? $billingSummary['ends_at']->format('d M, Y') : 'Sin fecha' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Evidencia</p>
                    <p class="text-sm font-black text-[#0F172A] mt-0.5">
                        @if($billingSummary['payment'])
                            Pago #{{ $billingSummary['payment']->id }}
                        @elseif($billingSummary['active_subscription'])
                            Sub #{{ $billingSummary['active_subscription']->id }}
                        @else
                            Sin registro
                        @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- Contacto --}}
        <div x-data="{ editingContact: false }" class="bg-slate-900 rounded-2xl p-5 text-white shadow-sm">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-[#38B2AC]">Datos de Contacto</h3>
                <button type="button" @click="editingContact = !editingContact" class="text-[9px] font-black uppercase tracking-widest text-white/40 hover:text-white transition-colors">
                    <span x-text="editingContact ? 'Cancelar' : 'Editar'"></span>
                </button>
            </div>

            <template x-if="!editingContact">
                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <p class="text-[10px] text-white/40 uppercase font-black">Email Corporativo</p>
                        <p class="text-sm truncate">{{ $tenant->email ?: 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-white/40 uppercase font-black">Línea Directa</p>
                        <p class="text-sm">{{ $tenant->phone ?: 'N/A' }}</p>
                    </div>
                </div>
            </template>

            <template x-if="editingContact">
                <form action="{{ route('admin.tenants.update', $tenant) }}" method="POST" class="space-y-3">
                    @csrf
                    @method('PATCH')
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-[10px] text-white/40 uppercase font-black">Email</label>
                            <input type="email" name="email" value="{{ $tenant->email }}" required class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-[#38B2AC] transition-all">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] text-white/40 uppercase font-black">Línea</label>
                            <input type="text" name="phone" value="{{ $tenant->phone }}" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-[#38B2AC] transition-all">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-[#38B2AC] hover:bg-[#2C9A94] text-white text-[10px] font-black uppercase tracking-widest py-2.5 rounded-xl transition-all">
                        Actualizar Datos
                    </button>
                </form>
            </template>
        </div>
    </div>

    {{-- ====== TABLAS (ancho completo) ====== --}}
    <div class="space-y-8">
        <div class="lg:col-span-8 space-y-8">
            
            {{-- TABLA: Historial de Suscripciones --}}
          <div 
    x-data="{ openAssignPlanModal: false, replacementPaymentId: {{ (int) old('replacement_payment_id', 0) ?: 'null' }}, paymentToRegister: null }"
    class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden"
>
    <div class="px-8 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <h2 class="font-black text-[11px] uppercase tracking-widest text-slate-500 underline decoration-[#38B2AC] decoration-2 underline-offset-4">
                Historial de Pagos
            </h2>

            @if($payments->contains(fn ($payment) => $payment->status === 'cancelled' && $payment->cancellation_reason !== 'replaced_by_manual_payment' && ! $payment->replaces_tenant_payment_id && ! $payments->contains('replaces_tenant_payment_id', $payment->id)))
                <form action="{{ route('admin.tenants.payments.clear-cancelled', $tenant) }}" method="POST" onsubmit="return confirm('¿Eliminar todos los registros de pago cancelados?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-[10px] font-black text-rose-500 uppercase tracking-widest hover:underline">
                        Limpiar Cancelados
                    </button>
                </form>
            @endif
        </div>

        <button 
            type="button"
            @click="openAssignPlanModal = true"
            class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-[#38B2AC] text-white text-[11px] font-black uppercase tracking-widest hover:bg-[#2C9A94] transition"
        >
            Agregar Plan
        </button>
    </div>

    <form method="POST" action="{{ route('admin.tenants.stripe-checkout-link', $tenant) }}" class="mx-8 mt-6 rounded-2xl border border-[#635BFF]/20 bg-[#635BFF]/5 p-5 grid grid-cols-1 md:grid-cols-[1fr_auto] gap-4 items-end">
        @csrf
        <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-[#635BFF] mb-2">Generar link Stripe para enviar</label>
            <select name="plan_id" required class="w-full rounded-2xl border-slate-200 focus:border-[#635BFF] focus:ring-[#635BFF]">
                <option value="">Selecciona un plan sincronizado</option>
                @foreach($plans as $plan)
                    @if($plan->stripe_price_id)
                        <option value="{{ $plan->id }}">{{ $plan->name }} - ${{ number_format($plan->price ?? 0, 2) }} {{ $plan->currency }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-5 py-3 rounded-2xl bg-[#635BFF] text-white text-[11px] font-black uppercase tracking-widest hover:bg-[#5148d8]">
            Generar link
        </button>
    </form>

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
 
    @if($errors->any())
        <div role="alert" class="mx-8 mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
    @endif

    <table class="w-full text-left">
    
       <thead>
    <tr class="bg-[#0F172A] text-[10px] font-black uppercase tracking-widest text-white">
        <th class="px-8 py-3.5">Fecha</th>
        <th class="px-8 py-3.5">Plan</th>
        <th class="px-8 py-3.5">Monto</th>
        <th class="px-8 py-3.5">Método</th>
        <th class="px-8 py-3.5">Status</th>
        <th class="px-8 py-3.5">Vence</th>
        <th class="px-8 py-3.5 text-right">Accion</th>
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
                <div class="mt-1 text-[10px] font-semibold text-slate-500">Pago #{{ $payment->id }}</div>
                @if($payment->replaces_tenant_payment_id)
                    <div class="mt-1 text-[10px] font-bold text-emerald-700">Sustituye Stripe #{{ $payment->replaces_tenant_payment_id }}</div>
                    <div class="text-[10px] font-semibold text-slate-500">Registrado por {{ $payment->createdBy?->name ?? 'usuario no disponible' }} · {{ $payment->created_at?->format('d/m/Y H:i') }}</div>
                @elseif($payment->cancellation_reason === 'replaced_by_manual_payment')
                    <div class="mt-1 text-[10px] font-bold text-amber-700">Checkout sustituido por pago manual #{{ $payments->firstWhere('replaces_tenant_payment_id', $payment->id)?->id ?? '—' }}</div>
                    <div class="text-[10px] font-semibold text-slate-500">Cancelado por {{ $payment->cancelledBy?->name ?? 'usuario no disponible' }} · {{ $payment->cancelled_at?->format('d/m/Y H:i') }}</div>
                @elseif($payment->cancellation_reason === 'stripe_checkout_expired')
                    <div class="mt-1 text-[10px] font-bold text-amber-700">Sesión Stripe vencida · {{ $payment->cancelled_at?->format('d/m/Y H:i') }}</div>
                @endif
                @if($payment->evidence_path)
                    <a href="{{ route('admin.tenants.payments.evidence', [$tenant, $payment]) }}" class="mt-1 inline-flex text-[10px] font-bold text-[#0F172A] underline hover:text-[#38B2AC]">Ver evidencia</a>
                @endif
            </td>

            <td class="px-8 py-4 font-black text-slate-900">
                ${{ number_format($payment->amount, 2) }}
                <span class="text-[10px] text-slate-400">{{ $payment->currency }}</span>
            </td>

           <td class="px-8 py-4">
    <div class="flex items-center gap-2">
        <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-widest">
            @if($payment->payment_method === 'trial')
                Trial
            @elseif($payment->payment_method === 'stripe_checkout')
                Stripe checkout
            @elseif($payment->payment_method === 'transfer')
                Transferencia
            @elseif($payment->payment_method === 'cash')
                Efectivo
            @else
                {{ $payment->payment_method ?? $payment->provider ?? 'manual' }}
            @endif
        </span>
        @if($payment->status === 'pending' && $payment->payment_reference && str_starts_with($payment->payment_reference, 'https://'))
            <a href="https://wa.me/?text={{ urlencode($payment->payment_reference) }}"
               target="_blank"
               title="Enviar por WhatsApp"
               class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-[#25D366] hover:bg-[#1ebe5d] transition shrink-0"
               x-data
               @click.prevent="navigator.clipboard.writeText('{{ $payment->payment_reference }}'); $el.classList.add('ring-2','ring-[#25D366]','ring-offset-1'); setTimeout(() => $el.classList.remove('ring-2','ring-[#25D366]','ring-offset-1'), 2000)"
               title="Copiar link de pago">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" class="w-3.5 h-3.5">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                    <path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.118 1.528 5.847L.057 23.882a.5.5 0 0 0 .606.63l6.204-1.63A11.94 11.94 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.894a9.877 9.877 0 0 1-5.031-1.378l-.36-.214-3.733.981.995-3.641-.235-.374A9.865 9.865 0 0 1 2.106 12C2.106 6.533 6.533 2.106 12 2.106S21.894 6.533 21.894 12 17.467 21.894 12 21.894z"/>
                </svg>
            </a>
        @endif
    </div>
</td>

            <td class="px-8 py-4">
                @if($payment->status === 'pending' && $payment->provider === 'manual')
                    <button type="button" @click="paymentToRegister = {{ $payment->id }}" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest cursor-pointer hover:ring-2 hover:ring-yellow-300
                @else
                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest
                @endif
                    @if($payment->status === 'paid')
                        bg-emerald-50 text-emerald-600
                    @elseif($payment->status === 'pending')
                        bg-yellow-50 text-yellow-600
                    @elseif($payment->status === 'failed')
                        bg-red-50 text-red-600
                    @else
                        bg-slate-100 text-slate-500
                    @endif
                " @if($payment->status === 'pending' && $payment->provider === 'manual') aria-label="Registrar pago pendiente" @endif>
                    @if($payment->status === 'paid')
                        Pagado
                    @elseif($payment->status === 'pending')
                        Pendiente
                    @elseif($payment->status === 'cancelled')
                        Cancelado
                    @else
                        {{ $payment->status }}
                    @endif
                @if($payment->status === 'pending' && $payment->provider === 'manual')
                    </button>
                @else
                    </span>
                @endif
            </td>

            <td class="px-8 py-4 font-bold text-slate-600">
                {{ $payment->period_ends_at?->format('d M, Y') ?? '—' }}
            </td>

            <td class="px-8 py-4 text-right">
                @if($payment->status === 'pending' && filter_var($tenant->email, FILTER_VALIDATE_EMAIL))
                    <form action="{{ route('admin.tenants.pending-payment-reminder', $tenant) }}" method="POST" class="inline-block mr-2" onsubmit="return confirm('¿Enviar recordatorio de pago a este cliente?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:bg-[#38B2AC]/10 hover:text-[#0F172A] transition"
                                title="Recordatorio de pago"
                                aria-label="Recordatorio de pago">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </button>
                    </form>
                @endif
                @if($payment->provider === 'manual' && $payment->status === 'pending')
                    <form action="{{ route('admin.tenants.payments.destroy', [$tenant, $payment]) }}" method="POST" onsubmit="return confirm('¿Eliminar este registro pendiente y su suscripción pendiente?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-rose-500 hover:text-rose-700 transition" title="Eliminar registro pendiente">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 01-1-1h-4a1 1 0 01-1 1v3M4 7h16"></path></svg>
                        </button>
                    </form>
                @elseif($payment->provider === 'stripe' && $payment->payment_method === 'stripe_checkout' && $payment->provider_payment_id && ($payment->status === 'pending' || ($payment->status === 'cancelled' && $payment->cancellation_reason === 'stripe_checkout_expired')))
                    <button type="button" @click="replacementPaymentId = {{ $payment->id }}" class="rounded-lg bg-amber-50 px-3 py-2 text-[10px] font-black uppercase text-amber-800 hover:bg-amber-100">Registrar por otro medio</button>
                @elseif($payment->status === 'cancelled' && $payment->cancellation_reason !== 'replaced_by_manual_payment' && ! $payments->contains('replaces_tenant_payment_id', $payment->id))
                    <form action="{{ route('admin.tenants.payments.destroy', [$tenant, $payment]) }}" method="POST" onsubmit="return confirm('¿Eliminar este registro de pago cancelado?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-rose-500 hover:text-rose-700 transition" title="Eliminar registro cancelado">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </form>
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="7" class="px-8 py-6 text-center text-slate-400 font-bold uppercase text-[10px] tracking-widest italic">
                Sin pagos registrados
            </td>
        </tr>
    @endforelse
</tbody>
    </table>

    @foreach($payments as $payment)
        @if($payment->status === 'pending' && $payment->provider === 'manual')
            <div x-show="paymentToRegister === {{ $payment->id }}" x-cloak @keydown.escape.window="paymentToRegister = null"
                 role="dialog" aria-modal="true" aria-labelledby="record-payment-title-{{ $payment->id }}"
                 class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
                <div class="absolute inset-0 bg-slate-900/50" @click="paymentToRegister = null"></div>
                <div class="relative max-h-full w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl md:p-8">
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <h3 id="record-payment-title-{{ $payment->id }}" class="text-lg font-black text-slate-900">Registrar pago recibido</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $tenant->business_name ?? $tenant->name }} · {{ $payment->plan?->name ?? 'Sin plan' }}</p>
                        </div>
                        <button type="button" @click="paymentToRegister = null" aria-label="Cerrar" class="text-2xl text-slate-500">×</button>
                    </div>

                    <div class="mb-5 rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-xs text-yellow-900">
                        <p class="font-bold">Pago #{{ $payment->id }} · Actualmente pendiente</p>
                        <p class="mt-1">Al confirmar se actualizará este mismo registro y se habilitará la suscripción correspondiente.</p>
                    </div>

                    <form method="POST" action="{{ route('admin.tenants.payments.record-payment', [$tenant, $payment]) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label class="block text-xs font-bold text-slate-700">Fecha de pago
                                <input type="date" name="paid_at" value="{{ old('paid_at', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required class="mt-1 w-full rounded-xl border-slate-200">
                            </label>
                            <label class="block text-xs font-bold text-slate-700">Monto recibido
                                <input type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount', (float) $payment->amount > 0 ? $payment->amount : '') }}" required class="mt-1 w-full rounded-xl border-slate-200">
                            </label>
                            <label class="block text-xs font-bold text-slate-700">Método de pago
                                <select name="payment_method" required class="mt-1 w-full rounded-xl border-slate-200">
                                    <option value="transfer">Transferencia</option>
                                    <option value="cash">Efectivo</option>
                                    <option value="card_manual">Tarjeta manual</option>
                                    <option value="other">Otro</option>
                                </select>
                            </label>
                            <label class="block text-xs font-bold text-slate-700">Folio o referencia (opcional)
                                <input type="text" name="payment_reference" maxlength="2000" class="mt-1 w-full rounded-xl border-slate-200">
                            </label>
                        </div>
                        <label class="block text-xs font-bold text-slate-700">Evidencia (opcional)
                            <input type="file" name="evidence" accept="image/jpeg,image/png,application/pdf" class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-xs">
                            <span class="mt-1 block text-[10px] font-medium text-slate-400">JPG, PNG o PDF · máximo 10 MB</span>
                        </label>
                        <label class="block text-xs font-bold text-slate-700">Notas (opcional)
                            <textarea name="notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-xl border-slate-200"></textarea>
                        </label>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="paymentToRegister = null" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700">Cancelar</button>
                            <button type="submit" class="rounded-xl bg-[#38B2AC] px-4 py-2 text-xs font-black text-white hover:bg-[#2C9A94]">Confirmar pago</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
        @if($payment->provider === 'stripe' && $payment->payment_method === 'stripe_checkout' && $payment->provider_payment_id && ($payment->status === 'pending' || ($payment->status === 'cancelled' && $payment->cancellation_reason === 'stripe_checkout_expired')))
            @php
                $replacementStartsAt = now()->startOfDay();
                $replacementEndsAt = match ($payment->plan?->billing_period) {
                    'yearly' => $replacementStartsAt->copy()->addYear()->subDay(),
                    default => $replacementStartsAt->copy()->addMonth()->subDay(),
                };
            @endphp
            <div x-show="replacementPaymentId === {{ $payment->id }}" x-cloak @keydown.escape.window="replacementPaymentId = null"
                 role="dialog" aria-modal="true" aria-labelledby="replace-payment-title-{{ $payment->id }}"
                 class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
                <div class="absolute inset-0 bg-slate-900/50" @click="replacementPaymentId = null"></div>
                <div class="relative max-h-full w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl md:p-8">
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <h3 id="replace-payment-title-{{ $payment->id }}" class="text-lg font-black text-slate-900">Registrar pago por otro medio</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $tenant->business_name ?? $tenant->name }} · {{ $payment->plan?->name ?? 'Sin plan' }}</p>
                        </div>
                        <button type="button" @click="replacementPaymentId = null" aria-label="Cerrar" class="text-2xl text-slate-500">×</button>
                    </div>
                    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900">
                        <p class="font-bold">Checkout Stripe #{{ $payment->id }} · ${{ number_format($payment->amount, 2) }} {{ $payment->currency }}</p>
                        <p class="mt-1 break-all">Sesión: {{ $payment->provider_payment_id }}</p>
                        <p class="mt-1">Periodo estimado original: {{ $payment->period_starts_at?->format('d/m/Y') ?? '—' }} al {{ $payment->period_ends_at?->format('d/m/Y') ?? '—' }}</p>
                        <p class="mt-2">Se verificará y expirará la sesión en Stripe antes de registrar el nuevo pago. Revisa las fechas de vigencia, especialmente si el enlace es antiguo.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.tenants.payments.replace-stripe', [$tenant, $payment]) }}" x-data="{ method: 'transfer', submitting: false }"
                          @submit="if (submitting) { $event.preventDefault(); return; } submitting = true" class="space-y-4">
                        @csrf
                        <input type="hidden" name="replacement_payment_id" value="{{ $payment->id }}">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label class="block text-xs font-bold text-slate-700">Forma de pago
                                <select name="payment_method" x-model="method" required class="mt-1 w-full rounded-xl border-slate-200">
                                    <option value="transfer">Transferencia</option><option value="cash">Efectivo</option>
                                    <option value="card_manual">Tarjeta manual</option><option value="other">Otro</option>
                                </select>
                            </label>
                            <label class="block text-xs font-bold text-slate-700">Fecha real del cobro
                                <input type="date" name="paid_at" value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required class="mt-1 w-full rounded-xl border-slate-200">
                            </label>
                            <label class="block text-xs font-bold text-slate-700">Importe ({{ $payment->currency }})
                                <input type="number" name="amount" min="0.01" step="0.01" value="{{ $payment->amount }}" required class="mt-1 w-full rounded-xl border-slate-200">
                            </label>
                            <label class="block text-xs font-bold text-slate-700">Referencia del pago
                                <input type="text" name="payment_reference" maxlength="255" :required="method === 'transfer' || method === 'card_manual'" placeholder="Folio o comprobante" class="mt-1 w-full rounded-xl border-slate-200">
                            </label>
                            <label class="block text-xs font-bold text-slate-700">Inicio de vigencia
                                <input type="date" name="starts_at" value="{{ $replacementStartsAt->format('Y-m-d') }}" required class="mt-1 w-full rounded-xl border-slate-200">
                            </label>
                            <label class="block text-xs font-bold text-slate-700">Fin de vigencia
                                <input type="date" name="ends_at" value="{{ $replacementEndsAt->format('Y-m-d') }}" required class="mt-1 w-full rounded-xl border-slate-200">
                            </label>
                        </div>
                        <label class="block text-xs font-bold text-slate-700">Notas
                            <textarea name="notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-xl border-slate-200"></textarea>
                        </label>
                        <label class="flex items-start gap-2 text-xs font-semibold text-slate-700">
                            <input type="checkbox" name="confirm_replacement" value="1" required class="mt-0.5 rounded border-slate-300">
                            Confirmo que recibimos este pago por otro medio y que se cancelará el intento Stripe #{{ $payment->id }}.
                        </label>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="replacementPaymentId = null" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700">Volver</button>
                            <button type="submit" :disabled="submitting" class="rounded-xl bg-amber-700 px-4 py-2 text-xs font-bold text-white disabled:opacity-50">Confirmar pago manual</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endforeach

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

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                        Tipo de registro
                    </label>
                    <select
                        name="billing_action"
                        required
                        class="w-full rounded-2xl border-slate-200 focus:border-[#38B2AC] focus:ring-[#38B2AC]"
                    >
                        <option value="trial">Trial sin cargo</option>
                        <option value="paid">Pago confirmado</option>
                        <option value="pending">Pago pendiente</option>
                    </select>
                    <p class="mt-2 text-[11px] font-semibold text-slate-400">
                        Trial fuerza monto $0. Pago pendiente no habilita acceso operativo hasta confirmarse.
                    </p>
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
                <div class="px-8 py-4 border-b border-[#0F172A] bg-[#0F172A] flex justify-between items-center">
                    <h2 class="font-black text-[11px] uppercase tracking-widest text-white">
                        Equipo de Trabajo
                    </h2>
                    <button @click="userModal = true" class="text-[10px] font-black text-[#38B2AC] uppercase tracking-tighter hover:text-white hover:underline">
                        + Agregar Miembro
                    </button>
                </div>
                <table class="w-full text-left">
                    <tbody class="divide-y divide-slate-100 italic">
                        @forelse($tenant->users as $user)
                            @php($needsActivation = !$user->is_active || !$user->invitation_accepted_at)
                            <tr x-data="{ editing: false }" class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-8 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center shrink-0" title="Imagen de usuario">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 7.5a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <template x-if="!editing">
                                                <div>
                                                    <p class="font-black text-[#0F172A] not-italic">{{ $user->name }}</p>
                                                    <p class="text-xs text-slate-400 font-medium">{{ $user->email }}</p>
                                                </div>
                                            </template>
                                            <template x-if="editing">
                                                <form id="edit-user-{{ $user->id }}" method="POST" action="{{ route('admin.tenants.users.update', [$tenant, $user]) }}" class="grid grid-cols-1 md:grid-cols-2 gap-3 not-italic">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input
                                                        type="text"
                                                        name="name"
                                                        value="{{ old('name', $user->name) }}"
                                                        required
                                                        class="w-full rounded-xl border-slate-200 px-3 py-2 text-xs font-bold text-[#0F172A] focus:border-[#38B2AC] focus:ring-[#38B2AC]"
                                                    >
                                                    <input
                                                        type="email"
                                                        name="email"
                                                        value="{{ old('email', $user->email) }}"
                                                        required
                                                        class="w-full rounded-xl border-slate-200 px-3 py-2 text-xs font-bold text-[#0F172A] focus:border-[#38B2AC] focus:ring-[#38B2AC]"
                                                    >
                                                </form>
                                            </template>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($user->getRoleNames() as $role)
                                            <span class="px-2 py-0.5 rounded bg-slate-100 text-[9px] font-black uppercase text-slate-600 tracking-tighter not-italic">
                                                {{ $role }}
                                            </span>
                                        @endforeach
                                        @if($needsActivation)
                                            <span class="px-2 py-0.5 rounded {{ $user->invitation_expires_at?->isPast() ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700' }} text-[9px] font-black uppercase tracking-tighter not-italic">
                                                {{ $user->invitation_expires_at?->isPast() ? 'Codigo expirado' : 'Pendiente activar' }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-8 py-4">
                                    <div class="flex items-center justify-end gap-3">
                                        <template x-if="!editing">
                                            <button
                                                type="button"
                                                @click="editing = true"
                                                class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-[9px] font-black uppercase tracking-widest hover:bg-slate-200 transition-colors not-italic"
                                            >
                                                Editar
                                            </button>
                                        </template>
                                        <template x-if="editing">
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="submit"
                                                    form="edit-user-{{ $user->id }}"
                                                    class="px-3 py-2 rounded-xl bg-[#38B2AC] text-white text-[9px] font-black uppercase tracking-widest hover:bg-[#2C9A94] transition-colors not-italic"
                                                >
                                                    Guardar
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="editing = false"
                                                    class="px-3 py-2 rounded-xl bg-slate-100 text-slate-500 text-[9px] font-black uppercase tracking-widest hover:bg-slate-200 transition-colors not-italic"
                                                >
                                                    Cancelar
                                                </button>
                                            </div>
                                        </template>
                                        @if($needsActivation)
                                            <form method="POST" action="{{ route('admin.tenants.users.resend-activation-code', [$tenant, $user]) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="px-3 py-2 rounded-xl bg-[#0F172A] text-white text-[9px] font-black uppercase tracking-widest hover:bg-slate-800 transition-colors not-italic">
                                                    Reenviar codigo
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.tenants.users.destroy', [$tenant, $user]) }}" onsubmit="return confirm('¿Eliminar este usuario del tenant? Esta accion no se puede deshacer.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-slate-300 hover:text-red-500 transition-colors" title="Eliminar usuario">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </form>
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
                                <option value="admin">Administrador (Acceso Total)</option>
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
