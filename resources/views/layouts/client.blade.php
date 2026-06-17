<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'VetSys Panel')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .sidebar-transition { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    </style>
</head>
@php
    $layoutTenant = auth()->user()?->tenant;
    $layoutThemePalette = \App\Support\TenantThemePalettes::normalize($layoutTenant?->theme_palette);
    $layoutTenantLogoUrl = $layoutTenant?->logoUrl();
@endphp
<body class="bg-slate-50 min-h-screen text-slate-900"
      data-theme-palette="{{ $layoutThemePalette }}"
      x-data="{ sidebarOpen: true }"
      @hasSection('contextual-tour') data-contextual-tour="@yield('contextual-tour')" @endif>
{{-- Toast de Notificación Global --}}
@if(session('success'))
    <div x-data="{ show: true }" 
         x-init="setTimeout(() => show = false, 3000)" 
         x-show="show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:leave="transition ease-in duration-200"
         class="fixed bottom-5 right-5 z-[100] theme-surface-dark px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3 border border-slate-700">
        <span class="text-xl">✅</span>
        <p class="text-[10px] font-black uppercase tracking-widest">{{ session('success') }}</p>
    </div>
@endif
@if(session('error') || $errors->any())
    <div x-data="{ show: true }"
         x-show="show"
         class="fixed bottom-5 right-5 z-[100] max-w-md bg-rose-600 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-start gap-3 border border-rose-500">
        <span class="text-xl">!</span>
        <div>
            <p class="text-[10px] font-black uppercase tracking-widest">No se pudo completar la operación</p>
            <p class="text-xs font-semibold mt-1">{{ session('error') ?: $errors->first() }}</p>
        </div>
        <button type="button" @click="show = false" class="ml-2 text-white/80 hover:text-white font-black">×</button>
    </div>
@endif
<div class="min-h-screen flex">

    {{-- Sidebar Estilo Premium Admin --}}
    <aside 
        :class="sidebarOpen ? 'w-64' : 'w-20'"
        class="sidebar-transition theme-bg-sidebar flex flex-col fixed inset-y-0 left-0 z-50 shadow-2xl">
        
        {{-- Logo Section --}}
    <a href="{{ route('client.profile.index') }}" class="block">
    <div class="px-4 py-5 border-b border-white/5 flex items-center overflow-hidden theme-bg-sidebar theme-bg-sidebar-hover transition-colors cursor-pointer">
        <div class="flex items-center gap-3 min-w-[200px]">
            {{-- Identidad visual del tenant --}}
            @if($layoutTenantLogoUrl)
                <img src="{{ $layoutTenantLogoUrl }}"
                     alt="{{ $layoutTenant->name ?? 'Tenant' }}"
                     class="flex-shrink-0 w-10 h-10 rounded-xl bg-white/95 object-contain p-1 theme-shadow-primary">
            @else
                <div class="flex-shrink-0 w-10 h-10 rounded-xl theme-bg-primary theme-text-primary-ink flex items-center justify-center font-black theme-shadow-primary">
                    {{ substr($layoutTenant->name ?? 'V', 0, 1) }}
                </div>
            @endif

            <div x-show="sidebarOpen" x-transition.opacity>
                <h1 class="font-bold text-lg leading-none text-white tracking-tight truncate max-w-[140px]">
                    {{ $layoutTenant->name ?? 'VetSys' }}
                </h1>

                <p class="text-[10px] uppercase tracking-widest theme-text-primary mt-1 font-black">
                    Panel Cliente
                </p>
            </div>
        </div>
    </div>
</a>

        {{-- Nav Links --}}
        <nav data-tour="main-navigation" class="flex-1 px-3 py-6 space-y-1 overflow-y-auto overflow-x-hidden custom-scrollbar">
                @php
                    $links = [
                        ['route' => 'client.dashboard', 'icon' => '▦', 'label' => 'Dashboard'],
                        // ['route' => 'client.profile.index', 'icon' => '◎', 'label' => 'Perfil'],
                        ['route' => 'client.customers.index', 'icon' => '👥', 'label' => 'Clientes'],
                        ['route' => 'client.animals.index', 'icon' => '🐕', 'label' => 'Pacientes'],
                        ['route' => 'client.clubes.index', 'icon' => '🏇', 'label' => 'Clubes'],
                        ['route' => 'client.ventas.index', 'icon' => '🛒', 'label' => 'Ventas'],
                        ['route' => 'client.servicios.index', 'icon' => '⚙️', 'label' => 'Servicios'],
                        ['route' => 'client.mi-configuracion.index', 'icon' => '🔧', 'label' => 'Configuración'],

                        // ['route' => 'client.facturacion.index', 'icon' => '🛒', 'label' => 'Facturación'],
                        // Agrega tus futuras opciones aquí manteniendo la misma estructura
                    ];
                @endphp

            @foreach($links as $link)
                <a href="{{ route($link['route']) }}"
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200
                   {{ request()->routeIs($link['route'])
                       ? 'theme-nav-active'
                       : 'text-slate-400 hover:bg-white/5 hover:text-white'
                   }}">
                    
                    <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center text-lg transition-transform group-hover:scale-110 {{ request()->routeIs($link['route']) ? 'theme-text-primary' : 'text-slate-400 group-hover:text-white' }}">
                        {{ $link['icon'] }}
                    </span>

                    <span x-show="sidebarOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap">
                        {{ $link['label'] }}
                    </span>
                </a>
            @endforeach
        </nav>

        {{-- Footer Sidebar con Datos de Sesión del Cliente --}}
        <div class="px-3 py-4 border-t border-white/5 theme-bg-sidebar-footer">
            <div class="flex items-center gap-3 px-3 py-2 overflow-hidden mb-2">
                <div class="w-8 h-8 rounded-lg theme-bg-primary flex-shrink-0 flex items-center justify-center text-xs font-black theme-text-primary-ink">
                    {{ substr(auth()->user()->name, 0, 1) }}
                </div>
                <div x-show="sidebarOpen" x-transition.opacity class="min-w-0">
                    <p class="text-xs font-bold text-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-[10px] theme-text-primary truncate opacity-80">
                        {{ auth()->user()->tenant->plan->name ?? 'Sin plan' }}
                    </p>
                </div>
            </div>

            {{-- Formulario de Cierre de Sesión Seguro --}}
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:bg-red-500/10 hover:text-red-400 transition-all group text-left">
                    <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center group-hover:rotate-12 transition-transform">↪</span>
                    <span x-show="sidebarOpen" x-transition.opacity class="font-medium text-sm">Cerrar sesión</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- Main Content Space --}}
    <div 
        :class="sidebarOpen ? 'ml-64' : 'ml-20'"
        class="sidebar-transition flex-1 flex flex-col min-w-0">

        {{-- Topbar con Blur y Botón Toggle --}}
        <header class="h-16 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-6 sticky top-0 z-40">
            <div class="flex items-center gap-4">
                {{-- BOTON DE COLAPSO (TOGGLE) --}}
                <button 
                    @click="sidebarOpen = !sidebarOpen"
                    class="p-2 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-600 transition-all outline-none theme-focus-primary">
                    <svg x-show="sidebarOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                    <svg x-show="!sidebarOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                    </svg>
                </button>
                
                {{-- Breadcrumb Dinámico --}}
                <nav class="flex text-xs font-medium text-slate-400 uppercase tracking-widest" aria-label="Breadcrumb">
                    <span>Panel</span>
                    <span class="mx-2">/</span>
                    <span class="text-slate-900">@yield('title', 'Dashboard')</span>
                </nav>
            </div>

            {{-- Información de usuario en el costado derecho --}}
            <div class="flex items-center gap-3">
                <button type="button"
                        data-tour-launch
                        hidden
                        class="inline-flex items-center gap-2 rounded-xl border theme-border-primary-soft theme-bg-primary-soft px-3.5 py-2.5 theme-text-primary-strong transition-all theme-bg-primary-soft-hover theme-focus-primary">
                    <span class="flex h-4 w-4 items-center justify-center rounded-full border border-current text-[10px] font-black">?</span>
                    <span class="hidden md:inline text-[10px] font-black uppercase tracking-widest">Guia</span>
                </button>

                <div class="relative" x-data="{ notificationsOpen: false }">
                    <button type="button"
                            data-tour="notifications"
                            @click="notificationsOpen = !notificationsOpen"
                            class="relative inline-flex items-center gap-2 rounded-xl border px-3.5 py-2.5 transition-all outline-none {{ ($layoutUnreadNotificationsCount ?? 0) > 0 ? 'bg-rose-50 border-rose-200 text-rose-700 shadow-sm' : 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-600' }}">
                        <span class="text-base leading-none">🔔</span>
                        <span class="hidden md:inline text-[10px] font-black uppercase tracking-widest">Notificaciones</span>
                        @if(($layoutUnreadNotificationsCount ?? 0) > 0)
                            <span class="ml-1 min-w-[20px] h-5 rounded-full bg-rose-600 px-1.5 text-[10px] font-black text-white flex items-center justify-center">
                                {{ $layoutUnreadNotificationsCount > 9 ? '9+' : $layoutUnreadNotificationsCount }}
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
                                <p class="text-xs font-black theme-text-heading uppercase tracking-widest">Notificaciones</p>
                                <p class="text-[11px] font-semibold text-slate-400">{{ $layoutUnreadNotificationsCount ?? 0 }} sin leer</p>
                            </div>
                            <a href="{{ route('client.notifications.index') }}" class="text-[10px] font-black uppercase tracking-widest theme-link-primary">Ver todas</a>
                        </div>

                        <div class="max-h-96 overflow-y-auto divide-y divide-slate-100">
                            @forelse(($layoutNotifications ?? collect()) as $notification)
                                <a href="{{ route('client.notifications.open', $notification) }}" class="block px-4 py-3 hover:bg-slate-50 transition-colors {{ $notification->read_at ? '' : 'theme-bg-primary-soft' }}">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-black theme-text-heading">{{ $notification->title }}</p>
                                            <p class="text-[11px] font-semibold text-slate-500 mt-1 leading-5">{{ $notification->body }}</p>
                                            <p class="text-[10px] font-bold text-slate-400 mt-2">{{ $notification->created_at->diffForHumans() }}</p>
                                        </div>
                                        @if(!$notification->read_at)
                                            <span class="mt-1 h-2 w-2 rounded-full theme-bg-primary flex-shrink-0"></span>
                                        @endif
                                    </div>
                                </a>
                            @empty
                                <div class="px-4 py-8 text-center">
                                    <p class="text-xs font-black theme-text-heading">Sin notificaciones</p>
                                    <p class="text-[11px] font-semibold text-slate-400 mt-1">Aqui apareceran avisos de telemedicina y otros eventos.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="text-right hidden sm:block">
                    <p class="text-xs font-bold text-slate-900 leading-none">{{ auth()->user()->name }}</p>
                    <p class="text-[10px] mt-1 uppercase tracking-wider font-semibold theme-text-primary">
                        {{ auth()->user()->tenant->name ?? '' }}
                    </p>
                </div>
            </div>
        </header>

        {{-- Contenedor Principal de Vistas --}}
        <main class="p-8 flex-1">
            <div class="max-w-7xl mx-auto">
                @yield('content')
            </div>
        </main>
    </div>

</div>

</body>
</html>
