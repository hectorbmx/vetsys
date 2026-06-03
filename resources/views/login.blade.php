<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VetAdmin – Iniciar Sesión</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; }

        /* Dotted background pattern */
        .dot-bg {
            background-color: #eef1f8;
            background-image: radial-gradient(circle, #c5cde0 1px, transparent 1px);
            background-size: 22px 22px;
        }

        /* Card entrance animation */
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-animate { animation: cardIn .45s cubic-bezier(.22,1,.36,1) both; }

        /* Input focus ring */
        .vet-input:focus {
            outline: none;
            border-color: #1d2b4f;
            box-shadow: 0 0 0 3px rgba(29,43,79,.12);
        }

        /* Button hover lift */
        .btn-primary {
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(29,43,79,.35);
        }
        .btn-primary:active { transform: translateY(0); }
    </style>
</head>
<body class="dot-bg min-h-screen flex flex-col items-center justify-center px-4 py-10">

    {{-- ── LOGIN CARD ── --}}
    <div class="card-animate bg-white rounded-3xl shadow-xl w-full max-w-sm px-8 py-10">

        {{-- Logo --}}
        <div class="flex justify-center mb-7">
            <div class="w-20 h-20 rounded-2xl bg-slate-100 flex items-center justify-center">
                {{-- Cross / paw icon --}}
                <svg width="44" height="44" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- vertical bar -->
                    <rect x="19" y="6" width="6" height="32" rx="3" fill="#1d2b4f"/>
                    <!-- horizontal bar -->
                    <rect x="6" y="19" width="32" height="6" rx="3" fill="#1d2b4f"/>
                    <!-- accent dots -->
                    <circle cx="11" cy="11" r="3.5" fill="#3b82f6"/>
                    <circle cx="33" cy="11" r="3.5" fill="#3b82f6"/>
                </svg>
            </div>
        </div>

        {{-- Heading --}}
        <h1 class="text-[1.65rem] font-bold text-slate-900 text-center leading-tight mb-1">
            Bienvenido a VetAdmin
        </h1>
        <p class="text-sm text-slate-500 text-center mb-8">
            Inicia sesión para gestionar tu clínica
        </p>

        {{-- Session / validation errors --}}
        @if ($errors->any())
            <div class="mb-5 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if (session('status'))
            <div class="mb-5 rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                {{ session('status') }}
            </div>
        @endif

        {{-- Form --}}
        <div class="space-y-5">
            @csrf

            {{-- Email --}}
            <div>
                <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Correo Electrónico
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-4 flex items-center text-slate-400 pointer-events-none">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H4.5a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5H4.5a2.25 2.25 0 0 0-2.25 2.25m19.5 0-9.75 6.75L2.25 6.75"/>
                        </svg>
                    </span>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        autocomplete="email"
                        required
                        value="{{ old('email') }}"
                        placeholder="ejemplo@clinica.com"
                        class="vet-input w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition-all duration-200"
                    >
                </div>
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Contraseña
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-4 flex items-center text-slate-400 pointer-events-none">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                        </svg>
                    </span>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        placeholder="••••••••"
                        class="vet-input w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition-all duration-200"
                    >
                </div>
            </div>

            {{-- Remember me + Forgot password --}}
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        name="remember"
                        class="w-4 h-4 rounded border-slate-300 text-slate-800 focus:ring-slate-500"
                    >
                    <span class="text-sm text-slate-600">Recordarme</span>
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                       class="text-sm font-semibold text-slate-800 hover:text-blue-600 transition-colors">
                        ¿Olvidaste tu contraseña?
                    </a>
                @endif
            </div>

            {{-- Submit --}}
            <button
                type="submit"
                onclick="this.closest('div.space-y-5').closest('div').querySelector('form') ? this.closest('form').submit() : submitLogin()"
                id="submit-btn"
                class="btn-primary w-full flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-6 py-3.5 text-sm font-bold text-white"
            >
                Iniciar Sesión
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                </svg>
            </button>
        </div>

        {{-- Divider --}}
        <div class="my-7 border-t border-slate-100"></div>

        {{-- Contact sales --}}
        <div class="space-y-2 text-center text-sm text-slate-500">
            <p>
                Tienes un codigo de activacion?
                <a href="{{ route('activation.show') }}" class="font-bold text-slate-800 hover:text-blue-600 transition-colors">
                    Activa tu cuenta
                </a>
            </p>
            <p>
                No tienes una cuenta?
                <a href="#" class="font-bold text-slate-800 hover:text-blue-600 transition-colors">
                    Contacta a ventas
                </a>
            </p>
        </div>
    </div>

    {{-- ── FOOTER NAV ── --}}
    <nav class="mt-8 flex items-center gap-3 text-sm text-slate-500">
        <a href="#" class="hover:text-slate-800 transition-colors">Privacidad</a>
        <span class="text-slate-300">•</span>
        <a href="#" class="hover:text-slate-800 transition-colors">Soporte</a>
        <span class="text-slate-300">•</span>
        <span class="flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 18c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3M3.5 9h17m-17 6h17"/>
            </svg>
            Español
        </span>
    </nav>

    {{-- ── COPYRIGHT ── --}}
    <footer class="mt-6 text-center text-xs text-slate-400 space-y-2">
        <p>© 2024 VetAdmin SaaS. Professional Clinical Care.</p>
        <div class="flex items-center justify-center gap-4">
            <a href="#" class="hover:text-slate-600 transition-colors">Privacy Policy</a>
            <a href="#" class="hover:text-slate-600 transition-colors">Terms of Service</a>
            <a href="#" class="hover:text-slate-600 transition-colors">Support</a>
        </div>
    </footer>

    {{-- Wrap the inputs in a real form for POST --}}
    <script>
        // Wrap the button click into a proper form submission
        document.getElementById('submit-btn').addEventListener('click', function () {
            const card = this.closest('.card-animate');
            const email = card.querySelector('#email').value;
            const password = card.querySelector('#password').value;
            const remember = card.querySelector('[name="remember"]').checked;
            const csrf = card.querySelector('[name="_token"]').value;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("login.store") }}';

            const addInput = (n, v) => {
                const i = document.createElement('input');
                i.type = 'hidden'; i.name = n; i.value = v;
                form.appendChild(i);
            };

            addInput('_token', csrf);
            addInput('email', email);
            addInput('password', password);
            if (remember) addInput('remember', '1');

            document.body.appendChild(form);
            form.submit();
        });
    </script>
</body>
</html>
