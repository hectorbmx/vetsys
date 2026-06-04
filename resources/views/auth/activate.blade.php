<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activar cuenta</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; }
        .dot-bg {
            background-color: #eef1f8;
            background-image: radial-gradient(circle, #c5cde0 1px, transparent 1px);
            background-size: 22px 22px;
        }
        .vet-input:focus {
            outline: none;
            border-color: #0f172a;
            box-shadow: 0 0 0 3px rgba(15, 23, 42, .12);
        }
    </style>
</head>
<body class="dot-bg min-h-screen flex items-center justify-center px-4 py-10">
    <main class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden">
        <div class="px-8 pt-9 pb-6 border-b border-slate-100">
            <div class="w-16 h-16 rounded-2xl bg-slate-900 flex items-center justify-center mb-6">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                Activar cuenta
            </h1>
            <p class="text-sm text-slate-500 font-medium mt-2">
                {{ isset($token) && $token ? 'Crea tu contrasena para activar tu cuenta.' : 'Ingresa el codigo de 6 digitos que te compartio el administrador y crea tu contrasena.' }}
            </p>
        </div>

        <form action="{{ route('activation.store') }}" method="POST" class="px-8 py-7 space-y-5">
            @csrf
            @if(isset($token) && $token)
                <input type="hidden" name="activation_token" value="{{ $token }}">
            @endif

            @if($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-bold mb-1">Revisa la informacion:</p>
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label for="email" class="block text-sm font-bold text-slate-700 mb-2">
                    Correo electronico
                </label>
                <input id="email"
                       type="email"
                       name="email"
                       value="{{ old('email', $tenant->email ?? '') }}"
                       autocomplete="email"
                       required
                       @if(isset($token) && $token) readonly @endif
                       placeholder="ejemplo@clinica.com"
                       class="vet-input w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800 placeholder-slate-400 transition">
            </div>

            @unless(isset($token) && $token)
                <div>
                    <label for="code" class="block text-sm font-bold text-slate-700 mb-2">
                        Codigo de activacion
                    </label>
                    <input id="code"
                           type="text"
                           name="code"
                           value="{{ old('code') }}"
                           inputmode="numeric"
                           maxlength="6"
                           pattern="[0-9]{6}"
                           autocomplete="one-time-code"
                           required
                           placeholder="000000"
                           class="vet-input w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-center text-2xl font-black tracking-[0.35em] text-slate-900 placeholder-slate-300 transition">
                </div>
            @endunless

            <div>
                <label for="password" class="block text-sm font-bold text-slate-700 mb-2">
                    Contrasena
                </label>
                <input id="password"
                       type="password"
                       name="password"
                       autocomplete="new-password"
                       required
                       class="vet-input w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 transition">
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-bold text-slate-700 mb-2">
                    Confirmar contrasena
                </label>
                <input id="password_confirmation"
                       type="password"
                       name="password_confirmation"
                       autocomplete="new-password"
                       required
                       class="vet-input w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 transition">
            </div>

            <button type="submit"
                    class="w-full rounded-xl bg-slate-900 px-6 py-3.5 text-sm font-black text-white hover:bg-slate-800 transition">
                Activar cuenta
            </button>

            <p class="text-center text-sm text-slate-500">
                Ya tienes cuenta activa?
                <a href="{{ route('login') }}" class="font-bold text-slate-900 hover:text-blue-600">
                    Inicia sesion
                </a>
            </p>
        </form>
    </main>
</body>
</html>
