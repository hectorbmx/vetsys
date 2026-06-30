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
        .sidebar-transition { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900" x-data="{ sidebarOpen: true }">

<div class="min-h-screen flex">

 {{-- Sidebar --}}
<aside 
    :class="sidebarOpen ? 'w-64' : 'w-20'"
    class="sidebar-transition bg-[#0F172A] flex flex-col fixed inset-y-0 left-0 z-50 shadow-2xl">
    
    {{-- Logo Section --}}
    <div class="px-4 py-5 border-b border-white/5 flex items-center overflow-hidden bg-[#0F172A]">
        <div class="flex items-center gap-3 min-w-[200px]">
            {{-- Usamos el color Tertiary (#38B2AC) para el logo para que resalte --}}
            <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-[#38B2AC] text-[#0F172A] flex items-center justify-center font-black shadow-lg shadow-[#38B2AC]/20">
                V
            </div>
            <div x-show="sidebarOpen" x-transition.opacity>
                <h1 class="font-bold text-lg leading-none text-white tracking-tight">VetSys</h1>
                <p class="text-[10px] uppercase tracking-widest text-[#38B2AC] mt-1 font-black">Admin Pro</p>
            </div>
        </div>
    </div>

    {{-- Nav Links --}}
    <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto overflow-x-hidden custom-scrollbar">
        @php
            $links = [
                ['route' => 'admin.dashboard', 'icon' => '▦', 'label' => 'Dashboard'],
                ['route' => 'admin.tenants.index', 'icon' => '👥', 'label' => 'Clientes'],
                ['route' => 'admin.planes.index', 'icon' => '📋', 'label' => 'Planes'],
                ['route' => 'admin.reportes.index', 'icon' => '📊', 'label' => 'Reportes'],
                ['route' => 'admin.configuracion.index', 'icon' => '⚙️', 'label' => 'Configuración'],
            ];
        @endphp

        @foreach($links as $link)
            <a href="{{ route($link['route']) }}"
               class="group flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200
               {{ request()->routeIs($link['route'])
                   ? 'bg-[#38B2AC]/10 text-[#38B2AC] shadow-[inset_0_0_0_1px_rgba(56,178,172,0.2)]'
                   : 'text-slate-400 hover:bg-white/5 hover:text-white'
               }}">
                
                <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center text-lg transition-transform group-hover:scale-110 {{ request()->routeIs($link['route']) ? 'text-[#38B2AC]' : 'text-slate-400 group-hover:text-white' }}">
                    {{ $link['icon'] }}
                </span>

                <span x-show="sidebarOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap">
                    {{ $link['label'] }}
                </span>
            </a>
        @endforeach
    </nav>

    {{-- Footer Sidebar --}}
    <div class="px-3 py-4 border-t border-white/5 bg-[#0B1222]">
        <div class="flex items-center gap-3 px-3 py-2 overflow-hidden mb-2">
            <div class="w-8 h-8 rounded-lg bg-[#38B2AC] flex-shrink-0 flex items-center justify-center text-xs font-black text-[#0F172A]">
                {{ substr(auth()->user()->name, 0, 1) }}
            </div>
            <div x-show="sidebarOpen" x-transition.opacity class="min-w-0">
                <p class="text-xs font-bold text-white truncate">{{ auth()->user()->name }}</p>
                <p class="text-[10px] text-[#38B2AC] truncate opacity-80">En línea</p>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:bg-red-500/10 hover:text-red-400 transition-all group text-left">
                <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center group-hover:rotate-12 transition-transform">â†ª</span>
                <span x-show="sidebarOpen" x-transition.opacity class="font-medium text-sm">Cerrar sesiÃ³n</span>
            </button>
        </form>
    </div>
</aside>

    {{-- Main Content --}}
    <div 
        :class="sidebarOpen ? 'ml-64' : 'ml-20'"
        class="sidebar-transition flex-1">

        {{-- Topbar --}}
        <header class="h-16 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-6 sticky top-0 z-40">
            <div class="flex items-center gap-4">
                {{-- BOTON DE COLAPSO --}}
                <button 
                    @click="sidebarOpen = !sidebarOpen"
                    class="p-2 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-600 transition-all">
                    <svg x-show="sidebarOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" /></svg>
                    <svg x-show="!sidebarOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" /></svg>
                </button>
                
                <nav class="flex text-xs font-medium text-slate-400 uppercase tracking-widest" aria-label="Breadcrumb">
                    <span>Admin</span>
                    <span class="mx-2">/</span>
                    <span class="text-slate-900">@yield('title')</span>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <div class="relative" x-data="{ notificationsOpen: false }">
                    <button type="button"
                            @click="notificationsOpen = !notificationsOpen"
                            class="relative inline-flex items-center gap-2 rounded-xl border px-3.5 py-2.5 transition-all outline-none {{ ($layoutAdminUnreadNotificationsCount ?? 0) > 0 ? 'bg-rose-50 border-rose-200 text-rose-700 shadow-sm' : 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-600' }}">
                        <span class="text-base leading-none">🔔</span>
                        {{-- <span class="hidden md:inline text-[10px] font-black uppercase tracking-widest">Notificaciones</span> --}}
                        @if(($layoutAdminUnreadNotificationsCount ?? 0) > 0)
                            <span class="ml-1 min-w-[20px] h-5 rounded-full bg-rose-600 px-1.5 text-[10px] font-black text-white flex items-center justify-center">
                                {{ $layoutAdminUnreadNotificationsCount > 9 ? '9+' : $layoutAdminUnreadNotificationsCount }}
                            </span>
                        @endif
                    </button>

                    <div x-show="notificationsOpen"
                         @click.outside="notificationsOpen = false"
                         x-transition
                         x-cloak
                         class="absolute right-0 mt-3 w-96 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl z-50">
                        <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-black text-[#0F172A] uppercase tracking-widest">Notificaciones SaaS</p>
                                <p class="text-[11px] font-semibold text-slate-400">{{ $layoutAdminUnreadNotificationsCount ?? 0 }} sin leer</p>
                            </div>
                            <a href="{{ route('admin.notifications.index') }}" class="text-[10px] font-black uppercase tracking-widest text-[#38B2AC] hover:text-[#0F172A]">Ver todas</a>
                        </div>

                        <div class="max-h-96 overflow-y-auto divide-y divide-slate-100">
                            @forelse(($layoutAdminNotifications ?? collect()) as $notification)
                                <a href="{{ route('admin.notifications.open', $notification) }}" class="block px-4 py-3 hover:bg-slate-50 transition-colors {{ $notification->read_at ? '' : 'bg-[#38B2AC]/5' }}">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-black text-[#0F172A]">{{ $notification->title }}</p>
                                            <p class="text-[11px] font-semibold text-slate-500 mt-1 leading-5">{{ $notification->body }}</p>
                                            <p class="text-[10px] font-bold text-slate-400 mt-2">{{ $notification->created_at->diffForHumans() }}</p>
                                        </div>
                                        @if(!$notification->read_at)
                                            <span class="mt-1 h-2 w-2 rounded-full bg-[#38B2AC] flex-shrink-0"></span>
                                        @endif
                                    </div>
                                </a>
                            @empty
                                <div class="px-4 py-8 text-center">
                                    <p class="text-xs font-black text-[#0F172A]">Sin notificaciones</p>
                                    <p class="text-[11px] font-semibold text-slate-400 mt-1">Aqui apareceran pagos y eventos del SaaS.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="text-right hidden sm:block">
                    <p class="text-xs font-bold text-slate-900 leading-none">{{ auth()->user()->name }}</p>
                    <p class="text-[10px] text-slate-500 mt-1">{{ auth()->user()->email }}</p>
                </div>
            </div>
        </header>

        <main class="p-8">
            <div class="max-w-7xl mx-auto">
                @yield('content')
            </div>
        </main>
    </div>

</div>

</body>
</html>
