@extends('layouts.client')

@section('title', 'Clubes')

@section('content')
<div class="space-y-8" x-data="{ clubModal: false, editClub: null, membersClub: null }">
    <div class="fixed top-4 right-4 z-[99] space-y-3 min-w-[320px]">
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition class="bg-white border-l-4 border-emerald-500 rounded-xl shadow-xl p-4 flex items-center justify-between border border-slate-100">
                <div>
                    <p class="text-xs font-black text-[#0F172A] uppercase tracking-wider">Operacion exitosa</p>
                    <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ session('success') }}</p>
                </div>
                <button @click="show = false" class="text-slate-400 hover:text-slate-600 text-xs ml-4">x</button>
            </div>
        @endif

        @if(session('error') || $errors->any())
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition class="bg-white border-l-4 border-red-500 rounded-xl shadow-xl p-4 border border-slate-100">
                <p class="text-xs font-black text-[#0F172A] uppercase tracking-wider">Revisa la informacion</p>
                <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ session('error') ?? 'Hay campos pendientes o repetidos.' }}</p>
            </div>
        @endif
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-[#0F172A] tracking-tighter">Clubes</h1>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Organiza mascotas por clubes y administra sus miembros.</p>
        </div>

        <button @click="clubModal = true" class="inline-flex items-center justify-center gap-2 bg-[#0F172A] text-white px-5 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg shadow-slate-200 transition-all">
            <span class="text-sm">+</span>
            Nuevo club
        </button>
    </div>

{{-- CARDS / TRES KPIS SUPERIORES CON DEGRADADOS DINÁMICOS --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    
    {{-- KPI 1: CLUBES ACTIVOS (TURQUESA) --}}
    <div class="group bg-gradient-to-br from-teal-500 to-emerald-600 border border-teal-500 rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl">
        <div class="space-y-1">
            <p class="text-[10px] font-black text-teal-100 uppercase tracking-widest">Clubes activos</p>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-white tracking-tight">{{ $clubs->where('is_active', true)->count() }}</span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-white/20 text-white flex items-center justify-center text-lg font-black group-hover:scale-110 transition-transform">C</div>
    </div>

    {{-- KPI 2: MIEMBROS ASIGNADOS (MORADO) --}}
    <div class="group bg-gradient-to-br from-indigo-600 to-violet-700 border border-indigo-700 rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl">
        <div class="space-y-1">
            <p class="text-[10px] font-black text-indigo-100 uppercase tracking-widest">Miembros asignados</p>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-white tracking-tight">{{ $animals->whereNotNull('club_id')->count() }}</span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-white/10 text-white flex items-center justify-center text-lg font-black group-hover:scale-110 transition-transform">M</div>
    </div>

    {{-- KPI 3: SIN CLUB (NARANJA) --}}
    <div class="group bg-gradient-to-br from-amber-500 to-orange-600 border border-orange-500 rounded-[24px] p-6 shadow-xl flex items-center justify-between transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl">
        <div class="space-y-1">
            <p class="text-[10px] font-black text-orange-100 uppercase tracking-widest">Sin club</p>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-white tracking-tight">{{ $animals->whereNull('club_id')->count() }}</span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-white/20 text-white flex items-center justify-center text-lg font-black group-hover:scale-110 transition-transform">S</div>
    </div>
</div>

    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Clubes registrados</h3>
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
                                    <div class="w-9 h-9 rounded-xl bg-[#38B2AC]/10 text-[#38B2AC] flex items-center justify-center font-black text-sm">
                                        {{ substr($club->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-black text-[#0F172A]">{{ $club->name }}</p>
                                        <p class="text-[10px] text-slate-400 font-semibold">Creado {{ $club->created_at->format('d/m/Y') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-xs font-semibold text-slate-500 max-w-sm">
                                {{ $club->description ?: 'Sin descripcion' }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs font-black text-[#0F172A]">{{ $club->animals_count }} miembro(s)</span>
                                    <span class="text-[10px] font-semibold text-slate-400">
                                        {{ $club->animals->take(2)->pluck('name')->join(', ') ?: 'Sin miembros' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded-full {{ $club->is_active ? 'text-emerald-700 bg-emerald-50' : 'text-slate-500 bg-slate-100' }}">
                                    {{ $club->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('client.clubes.edit', $club) }}"
                                       class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-[9px] font-black uppercase tracking-widest hover:bg-slate-200">
                                        Administrar
                                    </a>
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
            <div class="fixed inset-0 transition-opacity bg-[#0F172A]/80 backdrop-blur-sm" @click="clubModal = false"></div>
            <div class="relative inline-block w-full max-w-lg overflow-hidden text-left align-middle bg-white rounded-[24px] shadow-2xl border border-slate-100">
                <form action="{{ route('client.clubes.store') }}" method="POST">
                    @csrf
                    <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h3 class="text-lg font-black text-[#0F172A] tracking-tighter">Nuevo club</h3>
                        <button type="button" @click="clubModal = false" class="text-slate-400 hover:text-red-500">x</button>
                    </div>
                    <div class="p-8 space-y-5">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Nombre *</label>
                            <input type="text" name="name" value="{{ old('name') }}" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 outline-none">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Descripcion</label>
                            <textarea name="description" rows="3" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 outline-none resize-none">{{ old('description') }}</textarea>
                        </div>
                    </div>
                    <div class="px-8 py-6 bg-slate-50 flex items-center justify-end gap-3 border-t border-slate-100">
                        <button type="button" @click="clubModal = false" class="text-xs font-black uppercase tracking-widest text-slate-400 hover:text-slate-600">Cancelar</button>
                        <button type="submit" class="bg-[#0F172A] px-6 py-3.5 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800">Guardar club</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
