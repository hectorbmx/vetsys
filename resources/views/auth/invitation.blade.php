<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activar cuenta</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-6">

    <div class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden">

        <div class="p-8 border-b border-slate-100">
            <h1 class="text-2xl font-black text-slate-900">
                Activar cuenta
            </h1>

            <p class="text-sm text-slate-500 mt-2">
                Hola {{ $user->name }}, crea tu contraseña para acceder al sistema.
            </p>
        </div>

        <form action="{{ route('invitation.store', $token) }}"
              method="POST"
              class="p-8 space-y-5">

            @csrf

            @if(session('error'))
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
                    {{ session('error') }}
                </div>
            @endif

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
                <label class="block text-sm font-bold text-slate-700 mb-2">
                    Contraseña
                </label>

                <input type="password"
                       name="password"
                       class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900"
                       required>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">
                    Confirmar contraseña
                </label>

                <input type="password"
                       name="password_confirmation"
                       class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900"
                       required>
            </div>

            <button type="submit"
                    class="w-full rounded-xl bg-slate-900 px-5 py-3 text-white font-black hover:bg-slate-800">
                Activar cuenta
            </button>

        </form>

    </div>

</body>
</html> 