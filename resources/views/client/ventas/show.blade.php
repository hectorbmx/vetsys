@extends('layouts.client')

@section('content')
    <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">

        {{-- ENCABEZADO --}}
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-black text-[#0F172A] tracking-tight">{{ $note->folio }}</h1>
                <p class="text-sm text-slate-400 font-medium mt-0.5">{{ $note->date_at->format('d/m/Y') }}</p>
            </div>
            <a href="{{ route('client.ventas.index') }}"
               class="text-xs font-bold text-slate-500 hover:text-slate-700 border border-slate-200 px-3 py-1.5 rounded-lg transition-colors">
                ← Volver
            </a>
        </div>

        {{-- CLIENTE + ESTADO --}}
        <div class="bg-white border border-slate-200 rounded-[20px] shadow-sm p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Cliente</p>
                <a href="{{ route('client.customers.show', $note->customer->id) }}"
                   class="text-base font-black text-[#0F172A] hover:text-blue-600 transition-colors">
                    {{ $note->customer->full_name }}
                </a>
                <p class="text-xs text-slate-400 mt-0.5">📞 {{ $note->customer->phone ?? 'Sin teléfono' }}</p>
            </div>

            <div class="text-right space-y-1">
                @if($note->status === 'PAGADA')
                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full">🟢 Pagada</span>
                @elseif($note->status === 'PENDIENTE')
                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest text-amber-700 bg-amber-50 px-3 py-1 rounded-full">🟡 Crédito / Pendiente</span>
                @else
                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest text-slate-400 bg-slate-100 px-3 py-1 rounded-full">⚪ Cancelada</span>
                @endif
            </div>
        </div>

        {{-- DETALLES POR MASCOTA --}}
        @foreach($detailsByAnimal as $animalId => $details)
            <div class="bg-white border border-slate-200 rounded-[20px] shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                    <p class="text-xs font-black text-[#0F172A] uppercase tracking-wide">
                        🐾 {{ $details->first()->animal->name ?? 'Sin mascota' }}
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
                                    <span class="text-xs font-bold text-[#0F172A]">{{ $detail->catalogItem->name }}</span>
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
                                <td class="px-6 py-3 text-xs font-black text-[#0F172A] text-right">
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
                    <span class="font-black text-[#0F172A]">${{ number_format($note->total, 2) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="font-semibold text-slate-600">Pagado</span>
                    <span class="font-black text-emerald-600">${{ number_format($note->amount_paid, 2) }}</span>
                </div>
                <div class="border-t border-slate-100 pt-2 flex justify-between text-sm">
                    <span class="font-black text-slate-700">Saldo pendiente</span>
                    <span class="font-black {{ $note->balance > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                        ${{ number_format($note->balance, 2) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- PAGOS REGISTRADOS --}}
        @if($note->payments->isNotEmpty())
            <div class="bg-white border border-slate-200 rounded-[20px] shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                    <p class="text-xs font-black text-[#0F172A] uppercase tracking-wide">💳 Pagos Registrados</p>
                </div>
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-slate-100">
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Método</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Referencia</th>
                            <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Monto aplicado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($note->payments as $payment)
                            <tr>
                                <td class="px-6 py-3 text-xs font-bold text-[#0F172A]">
                                    {{ $payment->paymentMethod->name ?? '—' }}
                                </td>
                                <td class="px-6 py-3 text-xs text-slate-500 font-medium">
                                    {{ $payment->reference ?? '—' }}
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