<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago de nota {{ $paymentLink->note->folio }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-[#0F172A]">
    <main class="max-w-3xl mx-auto px-4 py-8 sm:py-12">
        <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Pago seguro</p>
                    <h1 class="text-xl font-black tracking-tight mt-1">{{ $paymentLink->tenant->name }}</h1>
                </div>
                <div class="text-left sm:text-right">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Nota</p>
                    <p class="text-sm font-black">{{ $paymentLink->note->folio }}</p>
                </div>
            </div>

            <div class="p-6 space-y-6">
                @if(session('error'))
                    <div class="bg-rose-50 border border-rose-100 text-rose-700 rounded-xl px-4 py-3 text-sm font-semibold">
                        {{ session('error') }}
                    </div>
                @endif

                @if(request('stripe_success'))
                    <div class="bg-emerald-50 border border-emerald-100 text-emerald-700 rounded-xl px-4 py-3 text-sm font-semibold">
                        Stripe recibio el pago. Estamos esperando la confirmacion final para marcar la nota como pagada.
                    </div>
                @endif

                @if(request('stripe_cancel'))
                    <div class="bg-amber-50 border border-amber-100 text-amber-700 rounded-xl px-4 py-3 text-sm font-semibold">
                        El pago no se completo. Puedes intentarlo de nuevo mientras el link siga vigente.
                    </div>
                @endif

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Cliente</p>
                        <p class="text-sm font-black mt-1">{{ $paymentLink->customer->full_name }}</p>
                    </div>
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total a pagar</p>
                        <p class="text-2xl font-black mt-1">${{ number_format((float) $paymentLink->amount, 2) }} {{ $paymentLink->currency }}</p>
                    </div>
                </div>

                <div class="border border-slate-100 rounded-xl overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">Concepto</th>
                                <th class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($paymentLink->note->details as $detail)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="text-xs font-bold">{{ $detail->catalogItem->name ?? 'Concepto' }}</p>
                                        <p class="text-[11px] text-slate-400 font-semibold">{{ $detail->animal->name ?? 'Paciente' }} · Cant. {{ $detail->quantity }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs font-black">${{ number_format((float) $detail->subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($paymentLink->status === 'paid' || $paymentLink->note->status === 'PAGADA')
                    <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 text-center">
                        <p class="text-sm font-black text-emerald-700">Pago confirmado</p>
                        <p class="text-xs text-emerald-600 font-semibold mt-1">Esta nota ya fue marcada como pagada.</p>
                    </div>
                @elseif($paymentLink->is_payable)
                    <form method="POST" action="{{ route('public.payments.checkout', $paymentLink->token) }}">
                        @csrf
                        <button type="submit" class="w-full bg-[#635BFF] hover:bg-[#5148d8] text-white rounded-xl px-5 py-4 text-xs font-black uppercase tracking-widest shadow-sm transition-colors">
                            Pagar con tarjeta
                        </button>
                    </form>
                    <p class="text-[11px] text-slate-400 font-semibold text-center">
                        El pago se procesa en Stripe. {{ $paymentLink->expires_at ? 'Link vigente hasta ' . $paymentLink->expires_at->format('d/m/Y H:i') . '.' : '' }}
                    </p>
                @else
                    <div class="bg-slate-100 border border-slate-200 rounded-xl p-4 text-center">
                        <p class="text-sm font-black text-slate-600">Link no disponible</p>
                        <p class="text-xs text-slate-500 font-semibold mt-1">Solicita un nuevo link de pago a la clinica.</p>
                    </div>
                @endif
            </div>
        </section>
    </main>
</body>
</html>
