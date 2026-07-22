<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'VetSys Panel')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
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
      x-data="{ sidebarOpen: false }"
      @hasSection('contextual-tour') data-contextual-tour="@yield('contextual-tour')" @endif>
{{-- Toast de Notificacion Global --}}
@if(session('success'))
    <div x-data="{ show: true }" 
         x-init="setTimeout(() => show = false, 3000)" 
         x-show="show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:leave="transition ease-in duration-200"
         class="fixed bottom-5 right-5 z-[100] theme-surface-dark px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3 border border-slate-700">
        <span class="text-xl">&#10003;</span>
        <p class="text-[10px] font-black uppercase tracking-widest">{{ session('success') }}</p>
    </div>
@endif
@if(session('error') || $errors->any())
    <div x-data="{ show: true }"
         x-show="show"
         class="fixed bottom-5 right-5 z-[100] max-w-md bg-rose-600 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-start gap-3 border border-rose-500">
        <span class="text-xl">!</span>
        <div>
            <p class="text-[10px] font-black uppercase tracking-widest">No se pudo completar la operacion</p>
            <p class="text-xs font-semibold mt-1">{{ session('error') ?: $errors->first() }}</p>
        </div>
        <button type="button" @click="show = false" class="ml-2 text-white/80 hover:text-white font-black">&times;</button>
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
        <nav data-tour="main-navigation" class="flex-1 px-3 py-6 space-y-1 overflow-visible custom-scrollbar">
                @php
                    $visibleMenuModules = \App\Support\TenantMenuModules::normalize(auth()->user()->tenant?->visible_menu_modules);
                    $links = [
                        ['module' => 'dashboard', 'route' => 'client.dashboard', 'icon' => '&#9638;', 'label' => 'Dashboard'],
                        
                        ['module' => 'customers', 'route' => 'client.customers.index', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
  <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
</svg>
', 'label' => 'Clientes'],
                        ['module' => 'animals', 'route' => 'client.animals.index', 'icon' => '🐎', 'label' => 'Caballos'],
                        ['module' => 'clubs', 'route' => 'client.clubes.index', 'icon' => '&#127943;', 'label' => 'Clubes'],
                        ['module' => 'sales', 'route' => 'client.ventas.index', 'icon' => '&#128722;', 'label' => 'Ventas'],
                        ['module' => 'services', 'route' => 'client.servicios.index', 'icon' => '&#128137;', 'label' => 'Servicios'],
                        ['route' => 'client.mi-configuracion.index', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
  <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
</svg>
', 'label' => 'Configuracion'],
                    ];

                    if (auth()->user()->can('view-appointments')) {
                        array_splice($links, 3, 0, [[
                            'module' => 'agenda',
                            'route' => 'client.agenda.index',
                            'active' => 'client.agenda.*',
                            'icon' => '&#128467;&#65039;',
                            'label' => 'Agenda',
                        ]]);
                    }

                    $links = array_values(array_filter($links, function ($link) use ($visibleMenuModules) {
                        return ! isset($link['module']) || in_array($link['module'], $visibleMenuModules, true);
                    }));
                @endphp
    @foreach ($links as $link)
        @php
            $isActive = request()->routeIs($link['active'] ?? $link['route']);
        @endphp

        <a href="{{ route($link['route']) }}"
           title="{{ $link['label'] }}"
           aria-label="{{ $link['label'] }}"
           class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200
                  {{ $isActive ? 'theme-nav-active' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            @if($isActive)
                <span class="absolute -left-3 top-1/2 h-8 w-1 -translate-y-1/2 rounded-r-full theme-bg-primary"></span>
            @endif
            
            <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center text-lg transition-transform group-hover:scale-110 {{ $isActive ? 'theme-text-primary' : 'text-slate-400 group-hover:text-white' }}">
                {!! $link['icon'] !!}
            </span>

            <span x-show="sidebarOpen" x-transition.opacity class="font-medium text-sm whitespace-nowrap">
                {{ $link['label'] }}
            </span>

            <span x-show="!sidebarOpen"
                  x-cloak
                  class="pointer-events-none absolute left-full top-1/2 ml-3 hidden -translate-y-1/2 rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white shadow-2xl ring-1 ring-white/10 group-hover:block">
                {{ $link['label'] }}
            </span>
        </a>
    @endforeach
        </nav>

        {{-- Footer Sidebar con Datos de Sesion del Cliente --}}
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

            {{-- Formulario de Cierre de Sesion Seguro --}}
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:bg-red-500/10 hover:text-red-400 transition-all group text-left">
                    <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center group-hover:rotate-12 transition-transform">&rarr;</span>
                    <span x-show="sidebarOpen" x-transition.opacity class="font-medium text-sm">Cerrar sesion</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- Main Content Space --}}
    <div 
        :class="sidebarOpen ? 'ml-64' : 'ml-20'"
        class="sidebar-transition flex-1 flex flex-col min-w-0">

        {{-- Topbar con Blur y Boton Toggle --}}
        <header class="h-16 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-6 sticky top-0 z-40">
            <div class="flex items-center gap-4">
                {{-- BOTON DE COLAPSO (TOGGLE) --}}
                <button
                    type="button"
                    @click="sidebarOpen = !sidebarOpen"
                    :aria-label="sidebarOpen ? 'Cerrar menu lateral' : 'Abrir menu lateral'"
                    :title="sidebarOpen ? 'Cerrar menu lateral' : 'Abrir menu lateral'"
                    class="inline-flex h-10 items-center gap-2 rounded-xl bg-slate-50 px-3 border border-slate-200 text-slate-600 hover:bg-slate-100 transition-all outline-none theme-focus-primary">
                    <svg x-show="sidebarOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                    <svg x-show="!sidebarOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                    </svg>
                    <span class="hidden sm:inline text-[10px] font-black uppercase tracking-widest" x-text="sidebarOpen ? 'Cerrar menu' : 'Abrir menu'"></span>
                </button>
                
                {{-- Breadcrumb Dinamico --}}
                <nav class="flex text-xs font-medium text-slate-400 uppercase tracking-widest" aria-label="Breadcrumb">
                    <span>Panel</span>
                    <span class="mx-2">/</span>
                    <span class="text-slate-900">@yield('title', 'Dashboard')</span>
                </nav>
            </div>

            {{-- Informacion de usuario en el costado derecho --}}
            <div class="flex items-center gap-3">
                <button type="button"
                        data-tour-launch
                        hidden
                        class="inline-flex h-10 items-center gap-2 rounded-xl border theme-border-primary-soft theme-bg-primary-soft px-3.5 py-2.5 theme-text-primary-strong transition-all theme-bg-primary-soft-hover theme-focus-primary"
                        aria-label="Abrir guia de la pantalla"
                        title="Abrir guia de la pantalla">
                    <span class="flex h-4 w-4 items-center justify-center rounded-full border border-current text-[10px] font-black">?</span>
                    <span class="hidden md:inline text-[10px] font-black uppercase tracking-widest">Guia</span>
                </button>

                <div class="relative" x-data="{ notificationsOpen: false }">
                    <button type="button"
                            data-tour="notifications"
                            @click="notificationsOpen = !notificationsOpen"
                            class="relative inline-flex items-center gap-2 rounded-xl border px-3.5 py-2.5 transition-all outline-none {{ ($layoutUnreadNotificationsCount ?? 0) > 0 ? 'bg-rose-50 border-rose-200 text-rose-700 shadow-sm' : 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-600' }}">
                        <span class="text-base leading-none">&#128276;</span>
                        {{-- <span class="hidden md:inline text-[10px] font-black uppercase tracking-widest">Notificaciones</span> --}}
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

        {{-- Accesos principales globales --}}
        <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-2">
            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-3 md:grid-cols-3">
                <a href="{{ route('client.customers.index') }}"
                   class="group rounded-xl border border-slate-200 bg-white/80 px-4 py-2.5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-teal-200 hover:bg-teal-50 hover:shadow-md">
                    <div class="flex items-center justify-center gap-3 text-center">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-500 transition-all group-hover:bg-white group-hover:text-teal-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11a4 4 0 10-8 0m8 0a4 4 0 11-8 0m8 0c2.5.4 4 1.8 4 4v2H4v-2c0-2.2 1.5-3.6 4-4m10-1.5a3 3 0 011.7 5.5M6 8.5A3 3 0 004.3 14" />
                            </svg>
                        </span>
                        <span>
                            <span class="block text-[11px] font-black uppercase tracking-widest theme-text-heading">Clientes</span>
                            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">Base de propietarios</span>
                        </span>
                    </div>
                </a>

                <a href="{{ route('client.animals.index') }}"
                   class="group rounded-xl border border-slate-200 bg-white/80 px-4 py-2.5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-teal-200 hover:bg-teal-50 hover:shadow-md">
                    <div class="flex items-center justify-center gap-3 text-center">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-500 transition-all group-hover:bg-white group-hover:text-teal-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 16.5V11l2.5-3.5h7L19 11v5.5M7.5 7.5l-1-3M15 7.5l1.5-3M8 16.5v3M17 16.5v3M9 11h.01M15 11h.01M10 14h4" />
                            </svg>
                        </span>
                        <span>
                            <span class="block text-[11px] font-black uppercase tracking-widest theme-text-heading">Caballos</span>
                            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">Pacientes activos</span>
                        </span>
                    </div>
                </a>

                <a href="{{ route('client.servicios.index') }}"
                   class="group rounded-xl border border-slate-200 bg-white/80 px-4 py-2.5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-teal-200 hover:bg-teal-50 hover:shadow-md">
                    <div class="flex items-center justify-center gap-3 text-center">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-500 transition-all group-hover:bg-white group-hover:text-teal-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h10M7 12h10M7 17h6M5 4h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z" />
                            </svg>
                        </span>
                        <span>
                            <span class="block text-[11px] font-black uppercase tracking-widest theme-text-heading">Servicios</span>
                            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">Catalogo de cobros</span>
                        </span>
                    </div>
                </a>
            </div>
        </div>

        {{-- Contenedor Principal de Vistas --}}
        <main class="p-8 flex-1">
            <div class="max-w-7xl mx-auto">
                @yield('content')
            </div>
        </main>
    </div>

</div>

@stack('scripts')
</body>
</html>
