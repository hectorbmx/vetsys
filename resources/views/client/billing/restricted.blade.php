@php
    $title = match ($billingStatus ?? null) {
        'pending_payment' => 'Pago pendiente',
        'trial_expired' => 'Trial vencido',
        'subscription_expired' => 'Plan vencido',
        default => 'Acceso restringido',
    };

    $description = match ($billingStatus ?? null) {
        'pending_payment' => 'Tu plan esta pendiente de pago. Completa el pago para habilitar nuevamente el sistema.',
        'trial_expired' => 'Tu periodo de prueba termino. Registra el pago de tu plan para continuar usando el sistema.',
        'subscription_expired' => 'La vigencia de tu plan termino. Renueva tu suscripcion para recuperar el acceso.',
        default => $message ?? 'Tu cuenta necesita completar la activacion del plan antes de usar el sistema.',
    };
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | VetSys</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <div class="fixed inset-0 overflow-hidden">
        <div class="absolute inset-0 bg-slate-200">
            <div class="mx-auto grid min-h-screen max-w-6xl grid-cols-1 gap-6 p-8 blur-sm opacity-50 md:grid-cols-3">
                <div class="rounded-3xl bg-white shadow-sm"></div>
                <div class="rounded-3xl bg-white shadow-sm md:col-span-2"></div>
                <div class="rounded-3xl bg-slate-900 shadow-sm"></div>
                <div class="rounded-3xl bg-white shadow-sm md:col-span-2"></div>
            </div>
        </div>
        <div class="absolute inset-0 bg-white/55 backdrop-blur-md"></div>
    </div>

    <main class="relative z-10 flex min-h-screen items-center justify-center px-5 py-10">
        <section class="w-full max-w-lg rounded-[28px] border border-white/70 bg-white/95 p-8 text-center shadow-2xl shadow-slate-300/70">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                </svg>
            </div>

            <p class="mt-6 text-[11px] font-black uppercase tracking-[0.24em] text-slate-400">Facturacion</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">{{ $title }}</h1>
            <p class="mt-4 text-sm font-semibold leading-6 text-slate-500">{{ $description }}</p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <a href="{{ route('client.profile.index') }}"
                   class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-[11px] font-black uppercase tracking-widest text-white transition hover:bg-slate-800">
                    Ir a facturacion
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-[11px] font-black uppercase tracking-widest text-slate-600 transition hover:bg-slate-50 sm:w-auto">
                        Cerrar sesion
                    </button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
