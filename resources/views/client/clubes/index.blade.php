@extends('layouts.client')

@section('title', 'Clubes')

@section('content')
<div class="space-y-8" x-data="{ clubModal: false, editClub: null, membersClub: null }">
    <div class="fixed top-4 right-4 z-[99] space-y-3 min-w-[320px]">
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition class="bg-white border-l-4 border-emerald-500 rounded-xl shadow-xl p-4 flex items-center justify-between border border-slate-100">
                <div>
                    <p class="text-xs font-black theme-text-heading uppercase tracking-wider">Operacion exitosa</p>
                    <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ session('success') }}</p>
                </div>
                <button @click="show = false" class="text-slate-400 hover:text-slate-600 text-xs ml-4">x</button>
            </div>
        @endif

        @if(session('error') || $errors->any())
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition class="bg-white border-l-4 border-red-500 rounded-xl shadow-xl p-4 border border-slate-100">
                <p class="text-xs font-black theme-text-heading uppercase tracking-wider">Revisa la informacion</p>
                <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ session('error') ?? 'Hay campos pendientes o repetidos.' }}</p>
            </div>
        @endif
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black theme-text-heading tracking-tighter">Clubes</h1>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Organiza mascotas por clubes y administra sus miembros.</p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
            <form method="GET" action="{{ route('client.clubes.index') }}" class="relative w-full sm:w-80">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 text-xs">🔍</span>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar club o descripción..." class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-12 py-3.5 text-xs font-semibold theme-text-heading placeholder-slate-400 theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-sm">
                @if(request()->filled('q'))
                    <a href="{{ route('client.clubes.index') }}" class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-rose-500 text-xs font-black">x</a>
                @endif
            </form>
            <button @click="clubModal = true" class="inline-flex items-center justify-center gap-2 theme-surface-dark px-5 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg shadow-slate-200 transition-all">
                <span class="text-sm">+</span>
                Nuevo club
            </button>
        </div>
    </div>

{{-- CARDS / TRES KPIS SUPERIORES CON DEGRADADOS DINÁMICOS --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    
    {{-- KPI 1: CLUBES ACTIVOS (TURQUESA) --}}
    <div class="group theme-surface-dark border border-slate-900 rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full theme-bg-primary-soft"></div>
        <div class="absolute right-8 bottom-8 w-16 h-16 rounded-full bg-white/10"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
            <div class="space-y-1">
                <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Clubes activos</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-white tracking-tight">{{ $clubs->where('is_active', true)->count() }}</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/20 text-white flex items-center justify-center text-lg font-black group-hover:scale-110 transition-transform">C</div>
        </div>
    </div>

    {{-- KPI 2: MIEMBROS ASIGNADOS (MORADO) --}}
    <div class="group theme-gradient-primary theme-border-primary rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl relative overflow-hidden">
        <div class="absolute -right-8 -bottom-8 w-32 h-32 rounded-full bg-white/20"></div>
        <div class="absolute -left-4 -top-4 w-20 h-20 rounded-full bg-white/10"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
            <div class="space-y-1">
                <p class="text-[10px] font-black text-white/80 uppercase tracking-widest">Miembros asignados</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-white tracking-tight">{{ $animals->whereNotNull('club_id')->count() }}</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/10 text-white flex items-center justify-center text-lg font-black group-hover:scale-110 transition-transform">M</div>
        </div>
    </div>

    {{-- KPI 3: SIN CLUB (NARANJA) --}}
    <div class="group theme-bg-primary-soft border theme-border-primary-soft rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full bg-white/15"></div>
            <div class="absolute left-8 bottom-8 w-16 h-16 rounded-full bg-white/10"></div>
            <div class="relative z-10 flex items-center justify-between w-full">
                <div class="space-y-1">
                    <p class="text-[10px] font-black theme-text-primary-strong uppercase tracking-widest">Sin club</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black theme-text-heading tracking-tight">{{ $animals->whereNull('club_id')->count() }}</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl theme-bg-primary text-white flex items-center justify-center text-lg font-black group-hover:scale-110 transition-transform">S</div>
            </div>
        </div>
</div>

    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Clubes registrados</h3>
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ $clubs->count() }} registro(s)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/20">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Club</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Descripcion</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Miembros</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($clubs as $club)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl theme-bg-primary-soft theme-text-primary flex items-center justify-center font-black text-sm">
                                        {{ substr($club->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-black theme-text-heading">{{ $club->name }}</p>
                                        <p class="text-[10px] text-slate-400 font-semibold">Creado {{ $club->created_at->format('d/m/Y') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-xs font-semibold text-slate-500 max-w-sm">
                                {{ $club->description ?: 'Sin descripcion' }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs font-black theme-text-heading">{{ $club->animals_count }} miembro(s)</span>
                                    <span class="text-[10px] font-semibold text-slate-400">
                                        {{ $club->animals->take(2)->pluck('name')->join(', ') ?: 'Sin miembros' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <form action="{{ route('client.clubes.toggle', $club->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="flex items-center gap-2 group focus:outline-none"
                                            title="{{ $club->is_active ? 'Click para Inactivar' : 'Click para Activar' }}">

                                        <div class="w-10 h-6 flex items-center p-1 rounded-full transition-colors duration-300 {{ $club->is_active ? 'theme-bg-primary' : 'bg-slate-300' }}">
                                            <div class="w-4 h-4 bg-white rounded-full shadow-sm transition-transform duration-300 transform {{ $club->is_active ? 'translate-x-4' : 'translate-x-0' }}"></div>
                                        </div>

                                        <span class="text-[10px] font-bold uppercase tracking-wider min-w-[50px] {{ $club->is_active ? 'theme-text-primary-strong' : 'text-slate-400' }}">
                                            {{ $club->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </button>
                                </form>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                  
                                         <a href="{{ route('client.clubes.edit', $club) }}"
                                        class="p-1.5 text-slate-400 theme-hover-text-primary transition-colors"
                                        title="Ver ficha">🔍</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <p class="text-sm font-bold text-slate-400">Aun no hay clubes registrados.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="clubModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity theme-overlay backdrop-blur-sm" @click="clubModal = false"></div>
            <div class="relative inline-block w-full max-w-lg overflow-hidden text-left align-middle bg-white rounded-[24px] shadow-2xl border border-slate-100">
                <form action="{{ route('client.clubes.store') }}" method="POST">
                    @csrf
                    <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h3 class="text-lg font-black theme-text-heading tracking-tighter">Nuevo club</h3>
                        <button type="button" @click="clubModal = false" class="text-slate-400 hover:text-red-500">x</button>
                    </div>
                    <div class="p-8 space-y-5">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Nombre *</label>
                            <input type="text" name="name" value="{{ old('name') }}" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary outline-none">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Descripcion</label>
                            <textarea name="description" rows="3" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary outline-none resize-none">{{ old('description') }}</textarea>
                        </div>
                    </div>
                    <div class="px-8 py-6 bg-slate-50 flex items-center justify-end gap-3 border-t border-slate-100">
                        <button type="button" @click="clubModal = false" class="text-xs font-black uppercase tracking-widest text-slate-400 hover:text-slate-600">Cancelar</button>
                        <button type="submit" class="theme-surface-dark px-6 py-3.5 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800">Guardar club</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
