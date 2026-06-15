@extends('layouts.client')

@section('title', 'Administrar Club')

@section('content')
<div class="space-y-6" x-data="{
    tab: '{{ request('tab', 'datos') }}',
    animalQuery: '',
    animalResults: [],
    selectedAnimals: @js($club->animals->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'customer' => $a->customer->full_name ?? 'N/A', 'type' => $a->animalType->name ?? 'N/A'])),
    searchUrl: @js(route('client.api.buscar-animales')),
    cogginModal: false,

    searchAnimals() {
        if (this.animalQuery.length < 2) {
            this.animalResults = [];
            return;
        }

        fetch(`${this.searchUrl}?q=${encodeURIComponent(this.animalQuery)}`)
            .then(response => response.json())
            .then(data => {
                // Filter out already selected animals
                this.animalResults = data.filter(a => !this.selectedAnimals.some(s => s.id === a.id));
            })
            .catch(() => { this.animalResults = []; });
    },

    addAnimal(animal) {
        this.selectedAnimals.push(animal);
        this.animalQuery = '';
        this.animalResults = [];
    },

    removeAnimal(index) {
        this.selectedAnimals.splice(index, 1);
    }
}">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white border border-slate-200 rounded-[24px] p-6">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl theme-bg-primary-soft theme-text-primary flex items-center justify-center font-black text-2xl border theme-border-primary-soft">
                {{ substr($club->name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-2xl font-black theme-text-heading tracking-tighter">{{ $club->name }}</h1>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-0.5">Administración integral del club y sus miembros.</p>
            </div>
        </div>
        <a href="{{ route('client.clubes.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-colors">
            ← Volver a la lista
        </a>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-3 rounded-2xl text-xs font-bold">
            ✓ {{ session('success') }}
        </div>
    @endif

    {{-- Tabs Navigation --}}
    <div class="flex gap-2 border-b border-slate-200">
        <button @click="tab = 'datos'" :class="tab === 'datos' ? 'theme-border-primary theme-text-primary' : 'border-transparent text-slate-400'" class="px-6 py-4 text-xs font-black uppercase tracking-widest border-b-2 transition-all">Datos del Club</button>
        <button @click="tab = 'miembros'" :class="tab === 'miembros' ? 'theme-border-primary theme-text-primary' : 'border-transparent text-slate-400'" class="px-6 py-4 text-xs font-black uppercase tracking-widest border-b-2 transition-all">Miembros ({{ $club->animals_count }})</button>
        <button @click="tab = 'coggins'" :class="tab === 'coggins' ? 'theme-border-primary theme-text-primary' : 'border-transparent text-slate-400'" class="px-6 py-4 text-xs font-black uppercase tracking-widest border-b-2 transition-all">Coggins</button>
    </div>

    {{-- Tabs Content --}}
    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        
        {{-- TAB: DATOS --}}
        <div x-show="tab === 'datos'" class="p-8 space-y-8">
            <form action="{{ route('client.clubes.update', $club) }}" method="POST" class="max-w-2xl space-y-6">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 gap-6">
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Nombre del Club *</label>
                        <input type="text" name="name" value="{{ old('name', $club->name) }}" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Descripción</label>
                        <textarea name="description" rows="4" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary outline-none resize-none">{{ old('description', $club->description) }}</textarea>
                    </div>

                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-black theme-text-heading uppercase tracking-widest">Estado del Club</p>
                            <p class="text-[10px] text-slate-400 font-semibold mt-0.5">Si está inactivo, no aparecerá en las opciones de selección.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" {{ $club->is_active ? 'checked' : '' }} class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 theme-peer-focus-ring-primary rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all theme-peer-checked-bg-primary"></div>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="submit" class="theme-surface-dark px-8 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 transition-all shadow-lg shadow-slate-100">
                        Guardar cambios
                    </button>
                </div>
            </form>
        </div>

        {{-- TAB: MIEMBROS --}}
        <div x-show="tab === 'miembros'" class="p-8 space-y-8" x-cloak>
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Administrar Miembros</h3>
                        <p class="text-[11px] text-slate-400 font-semibold mt-1">Busca y agrega nuevas mascotas al club o remueve las existentes.</p>
                    </div>
                </div>

                {{-- Autocomplete Search --}}
                <div class="relative max-w-xl">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <span class="text-slate-400 text-sm">🔍</span>
                    </div>
                    <input type="text" 
                           x-model="animalQuery" 
                           @input.debounce.300ms="searchAnimals()"
                           placeholder="Buscar mascota por nombre..." 
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-11 pr-4 py-4 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary outline-none transition-all">
                    
                    {{-- Search Results Dropdown --}}
                    <div x-show="animalResults.length > 0" 
                         class="absolute z-10 w-full mt-2 bg-white border border-slate-100 rounded-2xl shadow-2xl overflow-hidden divide-y divide-slate-50"
                         @click.outside="animalResults = []">
                        <template x-for="animal in animalResults" :key="animal.id">
                            <button @click="addAnimal(animal)" class="w-full px-5 py-4 text-left hover:bg-slate-50 flex items-center justify-between transition-colors">
                                <div>
                                    <p class="text-sm font-black theme-text-heading" x-text="animal.name"></p>
                                    <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">
                                        <span x-text="animal.type"></span> · <span x-text="animal.customer"></span>
                                    </p>
                                </div>
                                <span class="theme-text-primary font-black text-[10px] uppercase tracking-widest">+ Agregar</span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Selected Members List --}}
                <form action="{{ route('client.clubes.members.update', $club) }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PATCH')
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <template x-for="(animal, index) in selectedAnimals" :key="animal.id">
                            <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between group theme-hover-border-primary-soft transition-all">
                                <input type="hidden" name="animal_ids[]" :value="animal.id">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-white border border-slate-100 flex items-center justify-center theme-text-heading font-black text-sm">
                                        <span x-text="animal.name.charAt(0)"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-black theme-text-heading truncate" x-text="animal.name"></p>
                                        <p class="text-[10px] text-slate-400 font-semibold truncate" x-text="animal.customer"></p>
                                    </div>
                                </div>
                                <button type="button" @click="removeAnimal(index)" class="w-8 h-8 rounded-lg bg-white border border-slate-100 text-slate-400 hover:text-red-500 hover:border-red-100 flex items-center justify-center transition-all opacity-0 group-hover:opacity-100">
                                    ✕
                                </button>
                            </div>
                        </template>
                        <div x-show="selectedAnimals.length === 0" class="col-span-full py-12 text-center border-2 border-dashed border-slate-100 rounded-[24px]">
                            <p class="text-sm font-bold text-slate-300">No hay miembros en este club.</p>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="theme-surface-dark px-8 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 transition-all">
                            Actualizar miembros
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- TAB: COGGINS --}}
        <div x-show="tab === 'coggins'" class="p-8 space-y-8" x-cloak>
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Archivos Coggins</h3>
                    <p class="text-[11px] text-slate-400 font-semibold mt-1">Lista de documentos PDF asociados a este club.</p>
                </div>
                <button @click="cogginModal = true" class="theme-bg-primary text-white px-5 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-[#2C9A94] transition-all flex items-center gap-2">
                    <span class="text-sm">+</span>
                    Subir PDF
                </button>
            </div>

            <div class="overflow-hidden border border-slate-100 rounded-2xl">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Archivo</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Fecha</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse($club->coggins as $coggin)
                            <tr class="hover:bg-slate-50/50 transition-colors" x-data="{ copied: false }">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="text-rose-500 text-xl">📄</span>
                                        <div>
                                            <p class="text-sm font-black theme-text-heading">{{ $coggin->file_name }}</p>
                                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">PDF Documento</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-xs font-semibold text-slate-500">
                                    {{ $coggin->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button" 
                                                @click="navigator.clipboard.writeText('{{ url(Storage::url($coggin->file_path)) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                :class="copied ? 'bg-emerald-500 text-white' : 'bg-[#25D366]/10 text-[#25D366] hover:bg-[#25D366] hover:text-white'"
                                                class="px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5">
                                            <span x-show="!copied">
                                                <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                            </span>
                                            <span x-text="copied ? '¡Copiado!' : 'WhatsApp'"></span>
                                        </button>

                                        <a href="{{ Storage::url($coggin->file_path) }}" target="_blank" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-[9px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all">
                                            Ver PDF
                                        </a>
                                        <form action="{{ route('client.clubes.coggins.destroy', [$club, $coggin]) }}" method="POST" onsubmit="return confirm('¿Eliminar este archivo?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-3 py-2 rounded-xl bg-rose-50 text-rose-500 text-[9px] font-black uppercase tracking-widest hover:bg-rose-100 transition-all">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center text-sm font-bold text-slate-400">
                                    No hay archivos Coggins registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- MODAL COGGINS --}}
    <div x-show="cogginModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity theme-overlay backdrop-blur-sm" @click="cogginModal = false"></div>
            <div class="relative inline-block w-full max-w-lg overflow-hidden text-left align-middle bg-white rounded-[24px] shadow-2xl border border-slate-100">
                <form action="{{ route('client.clubes.coggins.store', $club) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h3 class="text-lg font-black theme-text-heading tracking-tighter">Subir archivo Coggins</h3>
                        <button type="button" @click="cogginModal = false" class="text-slate-400 hover:text-red-500">✕</button>
                    </div>
                    <div class="p-8 space-y-6">
                        <div class="space-y-4">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Seleccionar archivo PDF</label>
                            <div class="flex items-center justify-center w-full">
                                <label class="flex flex-col items-center justify-center w-full h-44 border-2 border-dashed border-slate-200 rounded-2xl cursor-pointer bg-slate-50 hover:bg-slate-100 theme-hover-border-primary-soft transition-all">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <span class="text-3xl mb-3">📄</span>
                                        <p class="text-xs font-black theme-text-heading uppercase tracking-widest mb-1">Haz clic para subir</p>
                                        <p class="text-[10px] text-slate-400 font-semibold">Solo archivos PDF (Max. 10MB)</p>
                                    </div>
                                    <input type="file" name="file" accept=".pdf" required class="hidden" />
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="px-8 py-6 bg-slate-50 flex items-center justify-end gap-3 border-t border-slate-100">
                        <button type="button" @click="cogginModal = false" class="text-xs font-black uppercase tracking-widest text-slate-400 hover:text-slate-600 transition-all">Cancelar</button>
                        <button type="submit" class="theme-bg-primary px-6 py-3.5 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-[#2C9A94] transition-all shadow-lg theme-shadow-primary">
                            Subir archivo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
