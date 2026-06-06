<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago de cuenta</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-[#0F172A]">
    <main class="max-w-xl mx-auto px-4 py-12">
        <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-6">
            <div>
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Pago seguro</p>
                <h1 class="text-xl font-black mt-1">{{ $paymentLink->tenant->name }}</h1>
                <p class="text-sm font-semibold text-slate-500 mt-1">{{ $paymentLink->customer->full_name }}</p>
            </div>

            @if(session('error'))
                <div class="bg-rose-50 border border-rose-100 text-rose-700 rounded-xl px-4 py-3 text-sm font-semibold">{{ session('error') }}</div>
            @endif
            @if(request('stripe_success'))
                <div class="bg-emerald-50 border border-emerald-100 text-emerald-700 rounded-xl px-4 py-3 text-sm font-semibold">Stripe recibio el pago. Estamos confirmando su aplicacion a la cuenta.</div>
            @endif
            @if(request('stripe_cancel'))
                <div class="bg-amber-50 border border-amber-100 text-amber-700 rounded-xl px-4 py-3 text-sm font-semibold">El pago no se completo. Puedes intentarlo nuevamente.</div>
            @endif

            <div class="bg-slate-50 border border-slate-100 rounded-xl p-5">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total a pagar</p>
                <p class="text-3xl font-black mt-1">${{ number_format((float) $paymentLink->amount, 2) }} {{ $paymentLink->currency }}</p>
                <p class="text-xs font-semibold text-slate-400 mt-2">El pago se aplicara a las notas pendientes mas antiguas.</p>
            </div>

            @if($paymentLink->status === 'paid')
                <div class="bg-emerald-50 text-emerald-700 rounded-xl p-4 text-center text-sm font-black">Pago confirmado</div>
            @elseif($paymentLink->is_payable)
                <form method="POST" action="{{ route('public.customer-payments.checkout', $paymentLink->token) }}">
                    @csrf
                    <button class="w-full bg-[#635BFF] hover:bg-[#5148d8] text-white rounded-xl px-5 py-4 text-xs font-black uppercase tracking-widest">Pagar con tarjeta</button>
                </form>
            @else
                <div class="bg-slate-100 text-slate-600 rounded-xl p-4 text-center text-sm font-black">Link no disponible</div>
            @endif
        </section>
    </main>
</body>
</html>
