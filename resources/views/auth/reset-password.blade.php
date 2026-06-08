<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña</title>
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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 0 0-9 0v3.75"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 10.5h10.5A2.25 2.25 0 0 1 19.5 12.75v6A2.25 2.25 0 0 1 17.25 21H6.75A2.25 2.25 0 0 1 4.5 18.75v-6A2.25 2.25 0 0 1 6.75 10.5Z"/>
                </svg>
            </div>

            <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                Restablecer contraseña
            </h1>

            <p class="text-sm text-slate-500 font-medium mt-2">
                Crea una nueva contraseña para recuperar el acceso a tu cuenta.
            </p>
        </div>

        <form action="{{ route('password.update') }}" method="POST" class="px-8 py-7 space-y-5">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            @if($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-bold mb-1">Revisa la información:</p>
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label for="email" class="block text-sm font-bold text-slate-700 mb-2">
                    Correo electrónico
                </label>

                <input id="email"
                       type="email"
                       name="email"
                       value="{{ old('email', $request->email) }}"
                       autocomplete="email"
                       required
                       placeholder="ejemplo@clinica.com"
                       class="vet-input w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800 placeholder-slate-400 transition">
            </div>

            <div>
                <label for="password" class="block text-sm font-bold text-slate-700 mb-2">
                    Nueva contraseña
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
                    Confirmar contraseña
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
                Guardar nueva contraseña
            </button>

            <p class="text-center text-sm text-slate-500">
                ¿Ya tienes acceso?
                <a href="{{ route('login') }}" class="font-bold text-slate-900 hover:text-blue-600">
                    Inicia sesión
                </a>
            </p>
        </form>
    </main>
</body>
</html>