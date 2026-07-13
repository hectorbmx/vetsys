@extends('layouts.client')

@section('content')
<script>
    localStorage.removeItem('vet_pos_backup');
</script>
    <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">

        {{-- ENCABEZADO --}}
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-black theme-text-heading tracking-tight">{{ $note->folio }}</h1>
                <p class="text-sm text-slate-400 font-medium mt-0.5">
                    {{ $note->date_at->format('d/m/Y') }}
                    @if($usesMonthlyCutoffBilling ?? false)
                        <span class="ml-2 inline-flex rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase tracking-widest text-slate-500">Respaldo interno</span>
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('client.ventas.ticket', $note) }}"
                   class="text-xs font-bold text-slate-500 hover:text-slate-700 border border-slate-200 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
                    Ver ticket
                </a>
                @if($usesMonthlyCutoffBilling ?? false)
                    <a href="{{ route('client.customers.show', ['customer' => $note->customer_id, 'tab' => 'notas']) }}"
                       class="text-xs font-bold theme-text-primary hover:theme-text-heading border theme-border-primary-soft px-3 py-1.5 rounded-lg transition-colors">
                        Ir a cuenta
                    </a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-100 text-emerald-700 rounded-xl px-4 py-3 text-sm font-semibold">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-rose-50 border border-rose-100 text-rose-700 rounded-xl px-4 py-3 text-sm font-semibold">
                {{ session('error') }}
            </div>
        @endif

        @if(!($usesMonthlyCutoffBilling ?? false) && session('payment_link_url'))
            <div x-data="{ copied: false, url: @js(session('payment_link_url')) }" class="bg-[#F4F3FF] border border-[#DAD7FE] rounded-[20px] shadow-sm p-5 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-[#635BFF]">Link Stripe generado</p>
                        <p class="text-xs font-semibold text-slate-500 mt-1">Comparte este link con el cliente para que pague desde su dispositivo.</p>
                    </div>
                    <button type="button"
                            @click="navigator.clipboard.writeText(url); copied = true; setTimeout(() => copied = false, 1800)"
                            class="bg-[#635BFF] hover:bg-[#5148d8] text-white px-4 py-2 rounded-xl text-[11px] font-black uppercase tracking-widest transition-colors">
                        <span x-text="copied ? 'Copiado' : 'Copiar link'"></span>
                    </button>
                </div>
                <input type="text" readonly :value="url" class="w-full bg-white border border-[#DAD7FE] rounded-xl px-4 py-2.5 text-xs font-semibold theme-text-heading">
            </div>
        @endif

        {{-- CLIENTE + ESTADO --}}
        <div class="bg-white border border-slate-200 rounded-[20px] shadow-sm p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Cliente</p>
                <a href="{{ route('client.customers.show', $note->customer->id) }}"
                   class="text-base font-black theme-text-heading theme-hover-text-primary transition-colors">
                    {{ $note->customer->full_name }}
                </a>
                <p class="text-xs text-slate-400 mt-0.5">{{ $note->customer->phone ?? 'Sin telefono' }}</p>
            </div>

            <div class="text-right space-y-1">
                @if($usesMonthlyCutoffBilling ?? false)
                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest text-slate-600 bg-slate-100 px-3 py-1 rounded-full">Cargo interno</span>
                @elseif($note->status === 'PAGADA')
                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full">Pagada</span>
                @elseif($note->status === 'PENDIENTE')
                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest text-amber-700 bg-amber-50 px-3 py-1 rounded-full">Credito / Pendiente</span>
                @else
                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest text-slate-400 bg-slate-100 px-3 py-1 rounded-full">Cancelada</span>
                @endif
            </div>
        </div>


        @if($usesMonthlyCutoffBilling ?? false)
            <div class="bg-slate-900 text-white rounded-[20px] shadow-sm p-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Modo cuentas mensuales</p>
                    <h2 class="mt-1 text-lg font-black">Esta nota alimenta la cuenta del cliente.</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-300">Los pagos se registran como abonos globales, no contra este folio.</p>
                </div>
                <div class="flex flex-col gap-2 sm:items-end">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Balance global</p>
                    <p class="text-2xl font-black">${{ number_format($customerAccountBalance ?? 0, 2) }}</p>
                    <div class="flex gap-2">
                        <a href="{{ route('client.customers.show', ['customer' => $note->customer_id, 'tab' => 'notas']) }}" class="rounded-xl bg-white px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-900">Ver cuentas</a>
                        <a href="{{ route('client.customers.show', ['customer' => $note->customer_id, 'tab' => 'pagos']) }}" class="rounded-xl border border-white/20 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white">Ver abonos</a>
                    </div>
                </div>
            </div>
        @endif

        {{-- DETALLES POR MASCOTA --}}
        @foreach($detailsByAnimal as $animalId => $details)
            <div class="bg-white border border-slate-200 rounded-[20px] shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                    <p class="text-xs font-black theme-text-heading uppercase tracking-wide">
                        {{ $details->first()->animal->name ?? 'Sin mascota' }}
                    </p>
                </div>
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-slate-100">
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Concepto</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Cant.</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Precio</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($details as $detail)
                            <tr>
                                <td class="px-6 py-3">
                                    <span class="text-xs font-bold theme-text-heading">{{ $detail->catalogItem->name }}</span>
                                    <span class="text-[10px] text-slate-400 block font-medium">
                                        {{ $detail->catalogItem->type === 'service' ? 'Servicio' : 'Producto' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-xs font-semibold text-slate-600 text-center">
                                    {{ $detail->quantity }}
                                </td>
                                <td class="px-6 py-3 text-xs font-semibold text-slate-600 text-right">
                                    ${{ number_format($detail->price_at_sale, 2) }}
                                </td>
                                <td class="px-6 py-3 text-xs font-black theme-text-heading text-right">
                                    ${{ number_format($detail->subtotal, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach

        {{-- RESUMEN FINANCIERO --}}
        <div class="bg-white border border-slate-200 rounded-[20px] shadow-sm p-6">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Resumen</p>
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="font-semibold text-slate-600">Total de la nota</span>
                    <span class="font-black theme-text-heading">${{ number_format($note->total, 2) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="font-semibold text-slate-600">{{ ($usesMonthlyCutoffBilling ?? false) ? 'Abonos aplicados internamente' : 'Pagado' }}</span>
                    <span class="font-black text-emerald-600">${{ number_format($note->amount_paid, 2) }}</span>
                </div>
                <div class="border-t border-slate-100 pt-2 flex justify-between text-sm">
                    <span class="font-black text-slate-700">{{ ($usesMonthlyCutoffBilling ?? false) ? 'Balance interno de la nota' : 'Saldo pendiente' }}</span>
                    <span class="font-black {{ $note->balance > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                        ${{ number_format($note->balance, 2) }}
                    </span>
                </div>
            </div>
        </div>

        @if(!($usesMonthlyCutoffBilling ?? false) && $note->balance > 0)
            <div class="bg-white border border-slate-200 rounded-[20px] shadow-sm p-6 space-y-4">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="border border-[#DAD7FE] bg-[#F4F3FF] rounded-2xl p-4 space-y-3">
                        <div>
                            <p class="text-[10px] font-black text-[#635BFF] uppercase tracking-widest">Cobro con Stripe</p>
                            <p class="text-xs text-slate-500 font-semibold mt-1">Genera un link para que el cliente liquide esta nota con tarjeta.</p>
                        </div>
                        <form method="POST" action="{{ route('client.ventas.stripe-payment-link', $note) }}">
                            @csrf
                            <button type="submit" class="bg-[#635BFF] hover:bg-[#5148d8] text-white px-4 py-2.5 rounded-xl text-[11px] font-black uppercase tracking-widest transition-colors">
                                Generar link
                            </button>
                        </form>
                    </div>

                    <div x-data="{ openManualPayment: false }" class="border border-emerald-100 bg-emerald-50 rounded-2xl p-4 space-y-3">
                        <div>
                            <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Pago manual a esta nota</p>
                            <p class="text-xs text-slate-500 font-semibold mt-1">Registra un pago directo para este folio.</p>
                        </div>
                        <button type="button"
                                @click="openManualPayment = true"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-xl text-[11px] font-black uppercase tracking-widest transition-colors">
                            Registrar pago
                        </button>

                        <div x-show="openManualPayment"
                             x-cloak
                             x-transition.opacity
                             class="fixed inset-0 z-50 flex items-center justify-center theme-overlay backdrop-blur-sm p-4"
                             @keydown.escape.window="openManualPayment = false">
                            <div class="bg-white rounded-[24px] shadow-2xl w-full max-w-md overflow-hidden"
                                 @click.outside="openManualPayment = false">
                                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Pago manual</p>
                                        <h3 class="text-lg font-black theme-text-heading mt-1">{{ $note->folio }}</h3>
                                    </div>
                                    <button type="button"
                                            @click="openManualPayment = false"
                                            class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 font-black transition-colors">
                                        x
                                    </button>
                                </div>

                                <form method="POST" action="{{ route('client.ventas.manual-payment', $note) }}" class="p-6 space-y-4">
                                    @csrf
                                    <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 flex justify-between items-center">
                                        <span class="text-xs font-black uppercase tracking-widest text-slate-400">Saldo actual</span>
                                        <span class="text-lg font-black text-rose-600">${{ number_format($note->balance, 2) }}</span>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Monto a aplicar</label>
                                        <input type="number"
                                               name="amount"
                                               min="0.01"
                                               max="{{ number_format($note->balance, 2, '.', '') }}"
                                               step="0.01"
                                               value="{{ number_format($note->balance, 2, '.', '') }}"
                                               required
                                               class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm font-black theme-text-heading theme-input">
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Metodo</label>
                                        <select name="payment_method_id"
                                                required
                                                class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold theme-text-heading bg-white theme-input">
                                            <option value="">Seleccionar...</option>
                                            @foreach($paymentMethods as $method)
                                                <option value="{{ $method->id }}">{{ $method->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Referencia</label>
                                        <input type="text"
                                               name="reference"
                                               maxlength="255"
                                               placeholder="Efectivo, transferencia, recibo, etc."
                                               class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading theme-input">
                                    </div>

                                    <div class="flex gap-3 pt-2">
                                        <button type="button"
                                                @click="openManualPayment = false"
                                                class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-3 rounded-xl text-xs font-black transition-colors">
                                            Cancelar
                                        </button>
                                        <button type="submit"
                                                class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-3 rounded-xl text-xs font-black transition-colors">
                                            Aplicar pago
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                @if($note->paymentLinks->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($note->paymentLinks as $link)
                            <div x-data="{ copied: false, url: @js(route('public.payments.show', $link->token)) }" class="border border-slate-100 bg-slate-50 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <p class="text-xs font-black theme-text-heading">${{ number_format((float) $link->amount, 2) }} {{ $link->currency }}</p>
                                    <p class="text-[11px] font-semibold text-slate-400">
                                        {{ ucfirst($link->status) }} &middot; {{ $link->created_at->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                                <button type="button"
                                        @click="navigator.clipboard.writeText(url); copied = true; setTimeout(() => copied = false, 1800)"
                                        class="bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 px-3 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition-colors">
                                    <span x-text="copied ? 'Copiado' : 'Copiar'"></span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if($note->payments->isNotEmpty())
            <div class="bg-white border border-slate-200 rounded-[20px] shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                    <p class="text-xs font-black theme-text-heading uppercase tracking-wide">{{ ($usesMonthlyCutoffBilling ?? false) ? 'Abonos aplicados internamente' : 'Pagos registrados' }}</p>
                </div>
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-slate-100">
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Metodo</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Referencia</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Monto aplicado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($note->payments as $payment)
                            <tr>
                                <td class="px-6 py-3 text-xs font-bold theme-text-heading">
                                    {{ $payment->paymentMethod->name ?? '-' }}
                                </td>
                                <td class="px-6 py-3 text-xs text-slate-500 font-medium">
                                    {{ $payment->reference ?? '-' }}
                                </td>
                                <td class="px-6 py-3 text-xs font-black text-emerald-600 text-right">
                                    ${{ number_format($payment->pivot->amount_applied, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>
@endsection

