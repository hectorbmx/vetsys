<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'VetSys')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
    <div class="min-h-screen flex flex-col">
        {{-- Header simplificado --}}
        <header class="bg-white border-b border-slate-200 py-4">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-center">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-[#38B2AC] text-[#0F172A] flex items-center justify-center font-black shadow-sm">
                        {{ substr($tenant->name ?? 'V', 0, 1) }}
                    </div>
                    <span class="font-bold text-lg text-slate-900 tracking-tight">{{ $tenant->name ?? 'VetSys' }}</span>
                </div>
            </div>
        </header>

        {{-- Contenido --}}
        <main class="flex-1 flex flex-col">
            @yield('content')
        </main>

        {{-- Footer --}}
        <footer class="bg-white border-t border-slate-100 py-6 text-center">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Powered by VetSys</p>
        </footer>
    </div>
</body>
</html>
