@extends('layouts.client')

@section('content')
<div x-data="{ tab: '{{ request('tab', 'notas') }}', ...pagoModal({{ $customer->id }}), openStatementModal: false }" class="p-6 max-w-7xl mx-auto space-y-6">

    {{-- CABECERA --}}
    <div class="bg-white border border-slate-200 rounded-[24px] p-6 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h1 class="text-2xl font-black text-[#0F172A]">{{ $customer->full_name }}</h1>
            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">
                {{ $customer->email ?? 'Sin correo' }} | {{ $customer->phone ?? 'Sin teléfono' }}
            </p>
        </div>
        <div class="flex items-center gap-3">
             <button @click="open = true" class="bg-[#38B2AC] hover:bg-[#2C9A94] text-white px-5 py-2.5 rounded-xl text-xs font-black transition-all shadow-sm">
                + Registrar Pago
            </button>
            <button @click="openStatementModal = true" class="bg-slate-700 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl text-xs font-black transition-all shadow-sm">
                Ver Estado
            </button>
            <a href="{{ route('client.customers.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-black transition-all">← Volver</a>
        </div>
    </div>

    {{-- FLASH --}}
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-3 rounded-2xl text-xs font-bold">
            ✓ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-5 py-3 rounded-2xl text-xs font-bold">{{ session('error') }}</div>
    @endif
    @if(session('customer_payment_link_url') || session('payment_link_url'))
        @php $generatedPaymentUrl = session('customer_payment_link_url') ?? session('payment_link_url'); @endphp
        <div x-data="{ copied: false, url: @js($generatedPaymentUrl) }" class="bg-[#F4F3FF] border border-[#DAD7FE] rounded-2xl p-5">
            <p class="text-[10px] font-black uppercase tracking-widest text-[#635BFF]">Link Stripe generado</p>
            <div class="flex gap-3 mt-3">
                <input readonly :value="url" class="flex-1 bg-white border border-[#DAD7FE] rounded-xl px-4 py-2.5 text-xs font-semibold">
                <button type="button" @click="navigator.clipboard.writeText(url); copied = true; setTimeout(() => copied = false, 1800)" class="bg-[#635BFF] text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase"><span x-text="copied ? 'Copiado' : 'Copiar'"></span></button>
            </div>
        </div>
    @endif

   {{-- KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        {{-- KPI 1: Adeudo General (ROSE) --}}
        <div class="group bg-gradient-to-br from-rose-500 to-red-600 border border-rose-600 rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl text-white">
            <div class="space-y-1">
                <p class="text-[10px] font-black text-rose-100 uppercase tracking-widest">Adeudo General</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black tracking-tight">${{ number_format($customer->outstanding_balance, 2) }}</span>
                </div>
                <p class="text-[10px] text-rose-100 font-medium">
                    {{ $customer->saleNotes->filter(fn ($note) => $note->balance > 0)->count() }} nota(s) pendiente(s)
                </p>
                @if($customer->credit_balance > 0)
                    <p class="text-[10px] font-black text-rose-100 mt-2">Saldo a favor: ${{ number_format($customer->credit_balance, 2) }}</p>
                @endif
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">⚠️</div>
        </div>

        {{-- KPI 2: Último Pago (EMERALD) --}}
        <div class="group bg-gradient-to-br from-emerald-500 to-teal-600 border border-emerald-600 rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl text-white">
            <div class="space-y-1">
                <p class="text-[10px] font-black text-emerald-100 uppercase tracking-widest">Último Pago</p>
                @php $lastPayment = $customer->payments->sortByDesc('created_at')->first(); @endphp
                @if($lastPayment)
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black tracking-tight">${{ number_format($lastPayment->amount, 2) }}</span>
                    </div>
                    <p class="text-[10px] text-emerald-100 font-medium">
                        {{ $lastPayment->created_at->format('d/m/Y') }} · {{ $lastPayment->paymentMethod->name ?? 'N/A' }}
                    </p>
                @else
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black tracking-tight">$0.00</span>
                    </div>
                    <p class="text-[10px] text-emerald-100 font-medium">Sin pagos registrados</p>
                @endif
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">💸</div>
        </div>

        {{-- KPI 3: Mascotas (INDIGO) --}}
        <div class="group bg-gradient-to-br from-indigo-600 to-violet-700 border border-indigo-700 rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl text-white">
            <div class="space-y-1">
                <p class="text-[10px] font-black text-indigo-100 uppercase tracking-widest">Pacientes Registrados</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black tracking-tight">{{ $customer->animals->count() }}</span>
                    <span class="text-[10px] font-medium text-indigo-100">paciente(s)</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">🐾</div>
        </div>
    </div>
    
    {{-- MODAL ESTADO DE CUENTA --}}
    <div x-show="openStatementModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-cloak>
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm shadow-xl" @click.outside="openStatementModal = false">
            <h3 class="text-sm font-black text-slate-800 mb-4">Seleccionar Período</h3>
            <form action="{{ route('client.customers.statement.generate', $customer->id) }}" method="GET" target="_blank">
                <div class="space-y-4">
                    <div>
                        <label class="text-[9px] font-bold text-slate-400 uppercase">Fecha Inicial</label>
                        <input type="date" name="date_from" value="{{ date('Y-m-01') }}" class="w-full border-slate-200 rounded-lg text-xs p-2 mt-1" required>
                    </div>
                    <div>
                        <label class="text-[9px] font-bold text-slate-400 uppercase">Fecha Final</label>
                        <input type="date" name="date_to" value="{{ date('Y-m-d') }}" class="w-full border-slate-200 rounded-lg text-xs p-2 mt-1" required>
                    </div>
                </div>
                <div class="flex gap-2 mt-6">
                    <button type="button" @click="openStatementModal = false" class="flex-1 px-4 py-2 text-xs font-bold text-slate-500 bg-slate-100 rounded-xl">Cancelar</button>
                    <button type="submit" class="flex-1 px-4 py-2 text-xs font-bold text-white bg-[#38B2AC] rounded-xl">Generar PDF</button>
                </div>
            </form>
        </div>
    </div>
        {{-- ============================================================
             MODAL DE PAGO
             ============================================================ --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
            @keydown.escape.window="open = false"
            style="display: none"
        >
            <div
                @click.outside="open = false"
                class="bg-white rounded-[24px] shadow-2xl w-full max-w-md overflow-hidden"
            >
                {{-- Header modal --}}
                <div class="px-6 pt-6 pb-4 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <h2 class="text-base font-black text-[#0F172A]">Registrar Pago</h2>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">{{ $customer->full_name }}</p>
                    </div>
                    <button @click="open = false" class="text-slate-400 hover:text-slate-600 transition-colors text-xl font-light">✕</button>
                </div>

                {{-- Formulario --}}
                <form method="POST" action="{{ route('client.customers.payments.store', $customer) }}">
                    @csrf
                    <div class="px-6 py-5 space-y-4">

                        {{-- Monto --}}
                        <div>
                            <label class="block text-[10px] text-slate-400 font-black uppercase tracking-widest mb-1.5">
                                Monto a pagar
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-sm">$</span>
                                <input
                                    type="number"
                                    name="amount"
                                    min="0.01"
                                    step="0.01"
                                    placeholder="0.00"
                                    x-model="amount"
                                    @input.debounce.400ms="fetchPreview()"
                                    class="w-full pl-7 pr-4 py-3 border border-slate-200 rounded-xl text-sm font-bold focus:outline-none focus:border-[#38B2AC] focus:ring-2 focus:ring-[#38B2AC]/20 transition-all"
                                    required
                                />
                            </div>
                        </div>

                        {{-- Método de pago --}}
                        <div>
                            <label class="block text-[10px] text-slate-400 font-black uppercase tracking-widest mb-1.5">
                                Método de Pago
                            </label>
                            <select
                                name="payment_method_id"
                                x-model="paymentMethodId"
                                @change="isCard = $event.target.selectedOptions[0]?.dataset.card === '1'"
                                class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none focus:border-[#38B2AC] focus:ring-2 focus:ring-[#38B2AC]/20 transition-all bg-white"
                                required
                            >
                                <option value="">Seleccionar...</option>
                                @foreach($paymentMethods as $method)
                                    @php $isCardMethod = str_contains(str($method->slug . ' ' . $method->name)->lower()->ascii()->toString(), 'tarjeta') || str_contains(str($method->slug . ' ' . $method->name)->lower()->ascii()->toString(), 'tarteja') || str_contains(str($method->slug . ' ' . $method->name)->lower()->ascii()->toString(), 'card') || str_contains(str($method->slug . ' ' . $method->name)->lower()->ascii()->toString(), 'stripe'); @endphp
                                    <option value="{{ $method->id }}" data-card="{{ $isCardMethod ? '1' : '0' }}">{{ $method->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Referencia (opcional) --}}
                        <div x-show="!isCard">
                            <label class="block text-[10px] text-slate-400 font-black uppercase tracking-widest mb-1.5">
                                Referencia <span class="text-slate-300 normal-case font-normal">(opcional)</span>
                            </label>
                            <input
                                type="text"
                                name="reference"
                                placeholder="N° de transferencia, cheque, etc."
                                class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-medium focus:outline-none focus:border-[#38B2AC] focus:ring-2 focus:ring-[#38B2AC]/20 transition-all"
                            />
                        </div>

                        {{-- PREVIEW FIFO --}}
                        <div
                            x-show="distribution.length > 0 || leftover > 0"
                            x-transition
                            class="bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden"
                        >
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 px-4 pt-4 pb-2">
                                Distribución del pago
                            </p>
                            <div class="divide-y divide-slate-100">
                                <template x-for="row in distribution" :key="row.folio">
                                    <div class="flex items-center justify-between px-4 py-3">
                                        <div>
                                            <p class="text-xs font-black text-slate-700" x-text="row.folio"></p>
                                            <p class="text-[10px] text-slate-400">
                                                Saldo: $<span x-text="fmt(row.balance)"></span>
                                                → $<span x-text="fmt(row.new_balance)" :class="row.new_balance <= 0 ? 'text-emerald-500 font-black' : 'text-slate-500'"></span>
                                            </p>
                                        </div>
                                        <span class="text-xs font-black text-emerald-600">
                                            -$<span x-text="fmt(row.amount_applied)"></span>
                                        </span>
                                    </div>
                                </template>

                                {{-- Sobrante --}}
                                <div x-show="leftover > 0" class="flex items-center justify-between px-4 py-3 bg-amber-50">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-amber-500">Sin aplicar</p>
                                    <span class="text-xs font-black text-amber-500">$<span x-text="fmt(leftover)"></span></span>
                                </div>
                            </div>
                        </div>

                        {{-- Estado de carga del preview --}}
                        <p x-show="loading" class="text-[10px] text-slate-400 text-center animate-pulse">
                            Calculando distribución...
                        </p>

                    </div>

                    {{-- Footer modal --}}
                    <div class="px-6 pb-6 flex gap-3">
                        <button
                            type="button"
                            @click="open = false"
                            class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-black transition-all"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="flex-1 py-3 bg-[#38B2AC] hover:bg-[#2C9A94] disabled:bg-slate-200 disabled:text-slate-400 text-white rounded-xl text-xs font-black transition-all"
                        >
                            Registrar pago / Generar link
                        </button>
                    </div>
                </form>
            </div>
            </div>

            {{-- NAVEGACIÓN DE TABS --}}
    <div class="flex gap-2 border-b border-slate-200">
        <button @click="tab = 'notas'" :class="tab === 'notas' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">Notas de Venta</button>
        <button @click="tab = 'mascotas'" :class="tab === 'mascotas' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">Mascotas</button>
        <button @click="tab = 'pagos'" :class="tab === 'pagos' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">Historial de Pagos</button>
        <button @click="tab = 'datos'" :class="tab === 'datos' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">Datos</button>
        <button @click="tab = 'configuracion'" :class="tab === 'configuracion' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">Configuracion</button>
    </div>

    {{-- CONTENIDO DE TABS --}}
    <div class="bg-white border border-slate-200 rounded-[24px] overflow-hidden">

        {{-- TAB: NOTAS --}}
        <div x-show="tab === 'notas'" class="p-6">
            <div class="flex justify-between items-center gap-4 mb-5">
                <div>
                    <h2 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Notas de Venta</h2>
                    <p class="text-[11px] text-slate-400 font-semibold mt-1">Ventas y saldos registrados para este cliente.</p>
                </div>
                <a href="{{ route('client.ventas.create', ['customer_id' => $customer->id]) }}"
                   class="bg-[#0F172A] hover:bg-slate-800 text-white px-4 py-2.5 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all shadow-sm">
                    Nueva nota
                </a>
            </div>

            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] text-slate-400 uppercase tracking-widest">
                        <th class="pb-4">Folio</th>
                        <th class="pb-4">Fecha</th>
                        <th class="pb-4">Total</th>
                        <th class="pb-4">Saldo</th>
                        <th class="pb-4">Estatus</th>
                        <th class="pb-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($customer->saleNotes as $note)
                        <tr x-data="{ openPayment: false, paymentMethodId: '', isCard: false }">
                            <td class="py-4 text-xs font-bold">{{ $note->folio }}</td>
                            <td class="py-4 text-xs font-medium text-slate-600">{{ $note->date_at->format('d/m/Y') }}</td>
                            <td class="py-4 text-xs font-black">${{ number_format($note->total, 2) }}</td>
                            <td class="py-4 text-xs font-bold {{ $note->balance <= 0 ? 'text-emerald-500' : 'text-rose-600' }}">
                                ${{ number_format($note->balance ?? 0, 2) }}
                            </td>
                            <td class="py-4">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest {{ $note->balance <= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                                    {{ $note->balance <= 0 ? 'Pagada' : 'Pendiente' }}
                                </span>
                            </td>
                            <td class="py-4 text-right">
                                @if($note->balance > 0)
                                    <button type="button" @click="openPayment = true" class="inline-flex items-center justify-center bg-[#38B2AC] hover:bg-[#2C9A94] text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-sm mr-2">Pagar</button>
                                @endif
                                <a href="{{ route('client.ventas.show', $note) }}"
                                   class="inline-flex items-center justify-center bg-[#0F172A] hover:bg-slate-800 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-sm">
                                    Ver nota
                                </a>

                                @if($note->balance > 0)
                                    <div x-show="openPayment" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-[#0F172A]/70 backdrop-blur-sm p-4" @keydown.escape.window="openPayment = false">
                                        <div class="bg-white rounded-[24px] shadow-2xl w-full max-w-md p-6 text-left" @click.outside="openPayment = false">
                                            <div class="flex justify-between items-start gap-4 mb-5">
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Cobrar nota</p>
                                                    <h3 class="text-lg font-black mt-1">{{ $note->folio }}</h3>
                                                    <p class="text-xs font-bold text-rose-500 mt-1">Saldo ${{ number_format($note->balance, 2) }}</p>
                                                </div>
                                                <button type="button" @click="openPayment = false" class="w-9 h-9 rounded-xl bg-slate-100 text-slate-500 font-black">x</button>
                                            </div>
                                            <form method="POST" action="{{ route('client.ventas.manual-payment', $note) }}" class="space-y-4">
                                                @csrf
                                                <input type="hidden" name="amount" value="{{ $note->balance }}">
                                                <div>
                                                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Metodo de pago</label>
                                                    <select name="payment_method_id" x-model="paymentMethodId" @change="isCard = $event.target.selectedOptions[0]?.dataset.card === '1'" required class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold bg-white">
                                                        <option value="">Seleccionar...</option>
                                                        @foreach($paymentMethods as $method)
                                                            @php $noteCardMethod = str_contains(str($method->slug . ' ' . $method->name)->lower()->ascii()->toString(), 'tarjeta') || str_contains(str($method->slug . ' ' . $method->name)->lower()->ascii()->toString(), 'tarteja') || str_contains(str($method->slug . ' ' . $method->name)->lower()->ascii()->toString(), 'card') || str_contains(str($method->slug . ' ' . $method->name)->lower()->ascii()->toString(), 'stripe'); @endphp
                                                            <option value="{{ $method->id }}" data-card="{{ $noteCardMethod ? '1' : '0' }}">{{ $method->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div x-show="!isCard">
                                                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Referencia opcional</label>
                                                    <input name="reference" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm">
                                                </div>
                                                <button type="submit" class="w-full bg-[#38B2AC] disabled:bg-slate-200 text-white disabled:text-slate-400 rounded-xl px-4 py-3 text-xs font-black uppercase tracking-widest">
                                                    Aplicar pago / Generar link
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endif

                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 text-xs text-slate-400">Sin notas registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- TAB: MASCOTAS --}}
<div x-show="tab === 'mascotas'" class="p-6">
  <div x-data="{ open: false }">
    {{-- MODAL: AGREGAR MASCOTA --}}
    <div 
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
        @keydown.escape.window="open = false"
    >
        <div
            class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden"
            @click.outside="open = false"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h3 class="text-sm font-black text-[#0F172A]">Nueva Mascota</h3>
                <button @click="open = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Formulario --}}
            <form action="{{ route('client.animals.store') }}" method="POST" class="px-6 py-5 space-y-4">
                @csrf
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                <input type="hidden" name="redirect_to" value="{{ route('client.customers.show', $customer) }}">

                {{-- Nombre + Tipo --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Nombre *</label>
                        <input
                            type="text"
                            name="name"
                            required
                            placeholder="Ej. Firulais"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#38B2AC]"
                        >
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Especie *</label>
                        <select
                            name="animal_type_id"
                            required
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#38B2AC]"
                        >
                            <option value="">Seleccionar...</option>
                            @foreach($animalTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Sexo + Fecha nacimiento --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Sexo *</label>
                        <select
                            name="sex"
                            required
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#38B2AC]"
                        >
                            <option value="">Seleccionar...</option>
                            <option value="male">Macho</option>
                            <option value="female">Hembra</option>
                            <option value="unknown">Desconocido</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Fecha de nacimiento</label>
                        <input
                            type="date"
                            name="birthdate"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#38B2AC]"
                        >
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Club</label>
                    <select
                        name="club_id"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#38B2AC]"
                    >
                        <option value="">Sin club</option>
                        @foreach($clubs as $club)
                            <option value="{{ $club->id }}">{{ $club->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Color + Peso --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Color</label>
                        <input
                            type="text"
                            name="color"
                            placeholder="Ej. Café con blanco"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#38B2AC]"
                        >
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Peso (kg)</label>
                        <input
                            type="number"
                            name="weight"
                            step="0.01"
                            min="0"
                            placeholder="Ej. 4.50"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#38B2AC]"
                        >
                    </div>
                </div>

                {{-- Microchip --}}
                <div>
                    <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Microchip</label>
                    <input
                        type="text"
                        name="microchip"
                        placeholder="Número de microchip"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#38B2AC]"
                    >
                </div>

                {{-- Notas --}}
                <div>
                    <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Notas</label>
                    <textarea
                        name="notes"
                        rows="2"
                        placeholder="Alergias, condiciones especiales..."
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#38B2AC] resize-none"
                    ></textarea>
                </div>

                {{-- Actions --}}
                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="open = false"
                        class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="px-5 py-2 bg-[#38B2AC] hover:bg-[#2C9A94] text-white text-xs font-black rounded-xl transition-colors"
                    >
                        Guardar mascota
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- CONTENIDO DEL TAB --}}
    <div >

        {{-- Botón agregar (siempre visible arriba a la derecha) --}}
        <div class="flex justify-end mb-4">
            <button
                @click="open = true"
                class="flex items-center gap-2 px-4 py-2 bg-[#38B2AC] hover:bg-[#2C9A94] text-white text-xs font-black rounded-xl transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Agregar mascota
            </button>
        </div>

        {{-- Tabla de mascotas --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] text-slate-400 uppercase tracking-widest">
                        <th class="px-4 py-3 pb-4">Nombre</th>
                        <th class="px-4 py-3 pb-4">Especie / Raza</th>
                        <th class="px-4 py-3 pb-4">Sexo</th>
                        <th class="px-4 py-3 pb-4">Edad</th>
                        <th class="px-4 py-3 pb-4">Club</th>
                        <th class="px-4 py-3 pb-4">Peso</th>
                        <th class="px-4 py-3 pb-4">Estatus</th>
                        <th class="px-4 py-3 pb-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($customer->animals as $animal)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-[#38B2AC]/10 text-[#38B2AC] flex items-center justify-center font-black text-xs">
                                        {{ substr($animal->name, 0, 1) }}
                                    </div>
                                    <span class="text-xs font-bold text-[#0F172A]">{{ $animal->name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-xs font-semibold text-slate-600">{{ $animal->animalType->name ?? 'N/A' }}</span>
                                <span class="block text-[10px] text-slate-400">{{ $animal->color ?? 'Sin color' }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-xs font-medium text-slate-600">
                                    {{ $animal->sex === 'male' ? 'Macho' : ($animal->sex === 'female' ? 'Hembra' : 'N/A') }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-xs font-medium text-slate-600">
                                    {{ $animal->birthdate ? \Carbon\Carbon::parse($animal->birthdate)->age . ' años' : 'N/A' }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                @if($animal->club)
                                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest text-[#38B2AC] bg-teal-50 px-2.5 py-1 rounded-full">
                                        {{ $animal->club->name }}
                                    </span>
                                @else
                                    <span class="text-[10px] text-slate-400 font-bold uppercase">Sin club</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-xs font-bold text-slate-700">
                                {{ $animal->weight ? $animal->weight . ' kg' : '--' }}
                            </td>
                            <td class="px-4 py-4">
                                <form action="{{ route('client.animals.toggle', $animal->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="flex items-center gap-2 group focus:outline-none">
                                        <div class="w-8 h-5 flex items-center p-1 rounded-full transition-colors duration-300 {{ $animal->status === 'active' ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                            <div class="w-3 h-3 bg-white rounded-full shadow-sm transition-transform duration-300 transform {{ $animal->status === 'active' ? 'translate-x-3' : 'translate-x-0' }}"></div>
                                        </div>
                                        <span class="text-[9px] font-black uppercase tracking-widest {{ $animal->status === 'active' ? 'text-emerald-600' : 'text-slate-400' }}">
                                            {{ $animal->status === 'active' ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('client.animals.edit', $animal) }}" class="inline-flex items-center justify-center bg-[#0F172A] hover:bg-slate-800 text-white px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all">
                                    Detalles
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-xs font-semibold text-slate-400">
                                No hay mascotas registradas para este cliente.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
        {{-- TAB: PAGOS --}}
        <div x-show="tab === 'pagos'" class="p-6">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] text-slate-400 uppercase tracking-widest border-b border-slate-100">
                        <th class="pb-4">Fecha</th>
                        <th class="pb-4">Referencia</th>
                        <th class="pb-4">Método</th>
                        <th class="pb-4 text-right">Aplicado</th>
                        <th class="pb-4 text-right">Saldo a favor</th>
                        <th class="pb-4 text-right">Monto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($customer->payments ?? [] as $payment)
                        <tr>
                            <td class="py-4 text-xs font-bold text-slate-700">{{ $payment->created_at->format('d/m/Y') }}</td>
                            <td class="py-4 text-xs font-medium text-slate-600">{{ $payment->reference ?? 'Pago aplicado' }}</td>
                            <td class="py-4 text-xs font-bold text-slate-500">
                                <span class="px-2 py-1 bg-slate-100 rounded-lg">{{ $payment->paymentMethod->name ?? 'N/A' }}</span>
                            </td>
                            <td class="py-4 text-xs font-black text-slate-600 text-right">${{ number_format($payment->amount_applied, 2) }}</td>
                            <td class="py-4 text-xs font-black text-amber-600 text-right">${{ number_format($payment->unapplied_amount, 2) }}</td>
                            <td class="py-4 text-xs font-black text-emerald-600 text-right">+${{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 text-xs text-slate-400 text-center">Sin pagos registrados para este cliente.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- TAB: DATOS --}}
        <div x-show="tab === 'datos'" class="p-6" x-cloak>
            <form action="{{ route('client.customers.update', $customer) }}" method="POST" class="space-y-6">
                @csrf
                @method('PATCH')
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Nombre --}}
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Nombre(s) *</label>
                        <input type="text" name="name" value="{{ old('name', $customer->name) }}" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-[#0F172A] focus:outline-none focus:border-[#38B2AC]">
                    </div>

                    {{-- Apellidos --}}
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Apellidos</label>
                        <input type="text" name="last_name" value="{{ old('last_name', $customer->last_name) }}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-[#0F172A] focus:outline-none focus:border-[#38B2AC]">
                    </div>

                    {{-- Correo --}}
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Correo Electrónico</label>
                        <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-[#0F172A] focus:outline-none focus:border-[#38B2AC]">
                    </div>

                    {{-- Teléfono --}}
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Teléfono Principal</label>
                        <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-[#0F172A] focus:outline-none focus:border-[#38B2AC]">
                    </div>

                    {{-- Teléfono Secundario --}}
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Teléfono Secundario</label>
                        <input type="text" name="secondary_phone" value="{{ old('secondary_phone', $customer->secondary_phone) }}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-[#0F172A] focus:outline-none focus:border-[#38B2AC]">
                    </div>

                    {{-- Dirección --}}
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Dirección</label>
                        <input type="text" name="address" value="{{ old('address', $customer->address) }}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-[#0F172A] focus:outline-none focus:border-[#38B2AC]">
                    </div>

                    {{-- Notas --}}
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Notas / Observaciones</label>
                        <textarea name="notes" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-[#0F172A] focus:outline-none focus:border-[#38B2AC] resize-none">{{ old('notes', $customer->notes) }}</textarea>
                    </div>
                </div>

                {{-- Status Toggle --}}
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-100 bg-slate-50 px-6 py-4">
                    <div>
                        <p class="text-xs font-black text-[#0F172A] uppercase tracking-widest">Estatus del Cliente</p>
                        <p class="text-[11px] font-semibold text-slate-400 mt-0.5">Define si el cliente está activo para realizar nuevas notas de venta.</p>
                    </div>
                    <div x-data="{ active: @js($customer->status === 'active') }" class="flex items-center">
                        <input type="hidden" name="status" :value="active ? 'active' : 'inactive'">
                        <button type="button" 
                                @click="active = !active" 
                                :class="active ? 'bg-emerald-500' : 'bg-slate-300'"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#38B2AC] focus:ring-offset-2">
                            <span :class="active ? 'translate-x-5' : 'translate-x-0'"
                                  class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                        </button>
                        <span class="ml-3 text-xs font-bold text-slate-600" x-text="active ? 'Activo' : 'Inactivo'"></span>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="bg-[#38B2AC] hover:bg-[#2C9A94] text-white px-8 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-sm">
                        Actualizar Datos
                    </button>
                </div>
            </form>
        </div>

        {{-- TAB: CONFIGURACION CONTABLE --}}
        <div x-show="tab === 'configuracion'" class="p-6" x-cloak>
            @php
                $accountSetting = $customer->accountSetting;
            @endphp

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1 bg-slate-50 border border-slate-100 rounded-2xl p-5">
                    <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Cuenta Contable</h3>
                    <p class="text-[11px] font-semibold text-slate-400 mt-2">
                        Define como se cortaran y conservaran los estados mensuales de este cliente.
                    </p>

                    <div class="mt-5 space-y-3 text-xs">
                        <div class="flex justify-between gap-4">
                            <span class="font-bold text-slate-400">Corte</span>
                            <span class="font-black text-[#0F172A]">Dia {{ $accountSetting->cutoff_day ?? 1 }}</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span class="font-bold text-slate-400">Credito</span>
                            <span class="font-black text-[#0F172A]">{{ $accountSetting->credit_days ?? 0 }} dias</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span class="font-bold text-slate-400">Estados</span>
                            <span class="font-black {{ optional($accountSetting)->is_statement_enabled ? 'text-emerald-600' : 'text-slate-400' }}">
                                {{ optional($accountSetting)->is_statement_enabled ? 'Activos' : 'Inactivos' }}
                            </span>
                        </div>
                    </div>
                </div>

                <form action="{{ route('client.customers.account-settings.update', $customer) }}" method="POST" class="lg:col-span-2 bg-white border border-slate-100 rounded-2xl p-5 space-y-5">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Dia de Corte</label>
                            <input type="number" name="cutoff_day" min="1" max="31" value="{{ old('cutoff_day', $accountSetting->cutoff_day ?? 1) }}" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-black text-[#0F172A] focus:outline-none focus:border-[#38B2AC]">
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Metodo Preferido</label>
                            <select name="preferred_payment_method_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-[#0F172A] focus:outline-none focus:border-[#38B2AC]">
                                <option value="">Sin preferencia</option>
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->id }}" @selected(old('preferred_payment_method_id', $accountSetting->preferred_payment_method_id ?? null) == $method->id)>
                                        {{ $method->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Dias de Credito</label>
                            <input type="number" name="credit_days" min="0" max="365" value="{{ old('credit_days', $accountSetting->credit_days ?? 0) }}" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-black text-[#0F172A] focus:outline-none focus:border-[#38B2AC]">
                        </div>
                    </div>

                    <label class="flex items-center justify-between gap-4 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 cursor-pointer">
                        <div>
                            <p class="text-xs font-black text-[#0F172A] uppercase tracking-widest">Generacion mensual</p>
                            <p class="text-[11px] font-semibold text-slate-400 mt-0.5">Preparado para generar y guardar estados de cuenta por corte.</p>
                        </div>
                        <input type="checkbox" name="is_statement_enabled" value="1" @checked(old('is_statement_enabled', optional($accountSetting)->is_statement_enabled)) class="rounded border-slate-300 text-[#38B2AC] focus:ring-[#38B2AC]">
                    </label>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-[#0F172A] hover:bg-slate-800 text-white px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] shadow-sm">
                            Guardar Configuracion
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-6 border border-slate-100 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-black text-[#0F172A] uppercase tracking-widest">Estados de Cuenta Guardados</h3>
                        <p class="text-[11px] font-semibold text-slate-400 mt-1">Genera el ultimo periodo cerrado segun el dia de corte configurado.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-[10px] font-bold text-slate-400">{{ $customer->statements->count() }} registro(s)</span>
                        @if($accountSetting)
                            <form action="{{ route('client.customers.statements.store', $customer) }}" method="POST">
                                @csrf
                                <button type="submit" class="bg-[#38B2AC] hover:bg-[#2C9A94] text-white px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-sm">
                                    Generar Estado
                                </button>
                            </form>
                        @else
                            <span class="text-[10px] font-black text-amber-600 bg-amber-50 px-3 py-2 rounded-xl">Configura primero</span>
                        @endif
                    </div>
                </div>
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] text-slate-400 uppercase tracking-widest border-b border-slate-100">
                            <th class="px-5 py-3">Periodo</th>
                            <th class="px-5 py-3">Consumo</th>
                            <th class="px-5 py-3">Pagos</th>
                            <th class="px-5 py-3">Saldo Final</th>
                            <th class="px-5 py-3">Estado</th>
                            <th class="px-5 py-3 text-right">PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse($customer->statements as $statement)
                            <tr>
                                <td class="px-5 py-4 text-xs font-bold text-[#0F172A]">
                                    {{ $statement->period_start->format('d/m/Y') }} - {{ $statement->period_end->format('d/m/Y') }}
                                </td>
                                <td class="px-5 py-4 text-xs font-black">${{ number_format($statement->period_charges, 2) }}</td>
                                <td class="px-5 py-4 text-xs font-black text-emerald-600">${{ number_format($statement->period_payments, 2) }}</td>
                                <td class="px-5 py-4 text-xs font-black text-[#0F172A]">${{ number_format($statement->ending_balance, 2) }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest bg-slate-100 text-slate-500">{{ $statement->status }}</span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    @if($statement->pdf_path)
                                        <a href="{{ route('client.customers.statements.pdf', [$customer, $statement]) }}" target="_blank" class="text-[10px] font-black uppercase tracking-widest text-[#38B2AC] hover:text-[#0F172A]">Abrir</a>
                                    @else
                                        <span class="text-[10px] font-bold text-slate-300">Sin PDF</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-xs font-bold text-slate-400">
                                    Aun no hay estados de cuenta guardados para este cliente.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
