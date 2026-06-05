@extends('layouts.client')

@section('title', 'Editar Mascota')

@section('content')
<div class="space-y-6" x-data="{
    tab: @js(session('animalTab', 'datos')),
    loading: false,
    videoUploading: false,
    videoFormOpen: false,
    videoPlayerOpen: false,
    playingVideoUrl: '',
    playingVideoTitle: '',
    radiologyStudyOpen: null,
    radiologyStudyTitle: '',
    radiologyStudyDate: '',
    radiologyStudyNotes: '',
    radiologyFormOpen: false,
    radiologyImageFormOpen: false,
    radiologyUploading: false,
    radiologyImageUrl: '',
    radiologyImageTitle: '',
    tenantQuery: '',
    tenantResults: [],
    selectedTenant: null,
    tenantSearchUrl: @js(route('client.api.telemedicine.tenants')),
    searchTenants() {
        if (this.selectedTenant || this.tenantQuery.length < 2) {
            this.tenantResults = [];
            return;
        }

        fetch(`${this.tenantSearchUrl}?q=${encodeURIComponent(this.tenantQuery)}`)
            .then(response => response.json())
            .then(data => { this.tenantResults = data; })
            .catch(() => { this.tenantResults = []; });
    },
    selectTenant(tenant) {
        this.selectedTenant = tenant;
        this.tenantQuery = tenant.label;
        this.tenantResults = [];
    },
    removeTenant() {
        this.selectedTenant = null;
        this.tenantQuery = '';
        this.tenantResults = [];
    }
}">
    <div x-show="videoUploading" x-cloak x-transition.opacity class="fixed inset-0 z-[120] flex items-center justify-center bg-[#0F172A]/70 px-4 backdrop-blur-sm">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#38B2AC]/10">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-[#38B2AC]/20 border-t-[#38B2AC]"></div>
            </div>
            <p class="mt-4 text-sm font-black uppercase tracking-widest text-[#0F172A]">Procesando video</p>
            <p class="mt-2 text-xs font-semibold text-slate-500">Optimizando y guardando el archivo. Esto puede tardar unos minutos.</p>
        </div>
    </div>

    <div x-show="radiologyUploading" x-cloak x-transition.opacity class="fixed inset-0 z-[120] flex items-center justify-center bg-[#0F172A]/70 px-4 backdrop-blur-sm">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#38B2AC]/10">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-[#38B2AC]/20 border-t-[#38B2AC]"></div>
            </div>
            <p class="mt-4 text-sm font-black uppercase tracking-widest text-[#0F172A]">Guardando radiologia</p>
            <p class="mt-2 text-xs font-semibold text-slate-500">Subiendo archivos RX al expediente.</p>
        </div>
    </div>

    <div class="fixed top-4 right-4 z-[99] space-y-3 min-w-[320px]">
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition class="bg-white border-l-4 border-emerald-500 rounded-xl shadow-xl p-4 flex items-center justify-between border border-slate-100">
                <div>
                    <p class="text-xs font-black text-[#0F172A] uppercase tracking-wider">Operacion Exitosa</p>
                    <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ session('success') }}</p>
                </div>
                <button @click="show = false" class="text-slate-400 hover:text-slate-600 text-xs ml-4">x</button>
            </div>
        @endif

        @if(session('error') || $errors->any())
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition class="bg-white border-l-4 border-red-500 rounded-xl shadow-xl p-4 border border-slate-100">
                <p class="text-xs font-black text-[#0F172A] uppercase tracking-wider">Revisa el formulario</p>
                <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ session('error') ?? 'Hay campos pendientes o con formato incorrecto.' }}</p>
            </div>
        @endif
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-[#38B2AC]/10 text-[#38B2AC] flex items-center justify-center font-black text-xl">
                {{ substr($animal->name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-3xl font-black text-[#0F172A] tracking-tighter">{{ $animal->name }}</h1>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">
                    {{ $animal->animalType->name ?? 'Sin especie' }} · {{ $animal->customer->full_name ?? 'Sin propietario' }}
                </p>
            </div>
        </div>

        <a href="{{ route('client.animals.index') }}" class="inline-flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl font-bold text-xs tracking-wide transition-all">
            Volver a Mascotas
        </a>
    </div>

    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-6 pt-4">
            <nav class="flex flex-wrap gap-1">
                <button type="button" @click="tab = 'datos'" :class="tab === 'datos' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Datos del Animal
                </button>
                <button type="button" @click="tab = 'historial'" :class="tab === 'historial' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Historial de Servicios
                </button>
                <button type="button" @click="tab = 'vacunacion'" :class="tab === 'vacunacion' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Cartas de Vacunacion
                </button>
                <button type="button" @click="tab = 'videos'" :class="tab === 'videos' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Videos
                </button>
                <button type="button" @click="tab = 'radiologia'" :class="tab === 'radiologia' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Radiologia
                </button>
                <button type="button" @click="tab = 'extra'" :class="tab === 'extra' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Mas Informacion
                </button>
                <button type="button" @click="tab = 'telemedicina'" :class="tab === 'telemedicina' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Telemedicina
                </button>
            </nav>
        </div>

        <div x-show="tab === 'datos'" class="p-6">
            <form action="{{ route('client.animals.update', $animal) }}" method="POST" @submit="loading = true" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Propietario *</label>
                        <select name="customer_id" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('customer_id', $animal->customer_id) == $customer->id)>{{ $customer->full_name }} ({{ $customer->phone }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Especie / Tipo *</label>
                        <select name="animal_type_id" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                            @foreach($animalTypes as $type)
                                <option value="{{ $type->id }}" @selected(old('animal_type_id', $animal->animal_type_id) == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Club</label>
                        <select name="club_id" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                            <option value="">Sin club</option>
                            @foreach($clubs as $club)
                                <option value="{{ $club->id }}" @selected(old('club_id', $animal->club_id) == $club->id)>{{ $club->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Nombre *</label>
                        <input type="text" name="name" value="{{ old('name', $animal->name) }}" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Sexo *</label>
                        <select name="sex" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                            <option value="male" @selected(old('sex', $animal->sex) === 'male')>Macho</option>
                            <option value="female" @selected(old('sex', $animal->sex) === 'female')>Hembra</option>
                            <option value="unknown" @selected(old('sex', $animal->sex) === 'unknown')>Desconocido</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Fecha de Nacimiento</label>
                        <input type="date" name="birthdate" value="{{ old('birthdate', optional($animal->birthdate)->format('Y-m-d')) }}" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Peso (kg)</label>
                        <input type="number" step="0.01" name="weight" value="{{ old('weight', $animal->weight) }}" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Color</label>
                        <input type="text" name="color" value="{{ old('color', $animal->color) }}" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Microchip</label>
                        <input type="text" name="microchip" value="{{ old('microchip', $animal->microchip) }}" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Estado *</label>
                        <select name="status" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                            <option value="active" @selected(old('status', $animal->status) === 'active')>Activo</option>
                            <option value="inactive" @selected(old('status', $animal->status) === 'inactive')>Inactivo</option>
                            <option value="deceased" @selected(old('status', $animal->status) === 'deceased')>Fallecido</option>
                            <option value="transferred" @selected(old('status', $animal->status) === 'transferred')>Transferido</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Notas Clinicas / Alergias</label>
                    <textarea name="notes" rows="4" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none resize-none">{{ old('notes', $animal->notes) }}</textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('client.animals.index') }}" class="px-5 py-3 rounded-xl bg-slate-100 text-slate-500 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-200">Cancelar</a>
                    <button type="submit" :disabled="loading" class="bg-[#0F172A] px-6 py-3 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 disabled:opacity-50">
                        Guardar Cambios
                    </button>
                </div>
            </form>
        </div>

        <div x-show="tab === 'historial'" class="p-6" x-cloak>
            <div class="overflow-hidden border border-slate-100 rounded-2xl">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Fecha</th>
                            <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Nota</th>
                            <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Servicio / Producto</th>
                            <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Cant.</th>
                            <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($serviceHistory as $detail)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-4 py-3 text-xs font-bold text-[#0F172A]">{{ optional($detail->note?->date_at)->format('d/m/Y') ?? '--' }}</td>
                                <td class="px-4 py-3 text-xs font-bold text-slate-500">{{ $detail->note->folio ?? 'Sin folio' }}</td>
                                <td class="px-4 py-3 text-xs font-bold text-[#0F172A]">{{ $detail->catalogItem->name ?? 'Concepto eliminado' }}</td>
                                <td class="px-4 py-3 text-xs font-bold text-right text-slate-500">{{ $detail->quantity }}</td>
                                <td class="px-4 py-3 text-xs font-black text-right text-[#0F172A]">${{ number_format($detail->subtotal, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-xs font-bold text-slate-400">Este paciente todavia no tiene servicios registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="tab === 'vacunacion'" class="p-6 space-y-6" x-cloak>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <form action="{{ route('client.animals.vaccination-letters.store', $animal) }}" method="POST" enctype="multipart/form-data" class="lg:col-span-1 border border-slate-200 rounded-2xl p-5 space-y-4 bg-slate-50/40">
                    @csrf

                    <div>
                        <p class="text-sm font-black text-[#0F172A]">Nueva carta</p>
                        <p class="text-[11px] text-slate-400 font-semibold mt-1">Solo se conservan 2 cartas. Al subir una tercera se reemplaza la segunda.</p>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Fecha *</label>
                        <input type="date" name="date" value="{{ old('date') }}" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Imagen *</label>
                        <input type="file" name="image" accept="image/png,image/jpeg,image/webp" required class="block w-full text-xs font-bold text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-[#0F172A] file:px-4 file:py-2.5 file:text-xs file:font-black file:uppercase file:tracking-widest file:text-white hover:file:bg-slate-800">
                        <p class="text-[10px] text-slate-400 font-semibold">Formatos: JPG, PNG o WEBP. Maximo 5 MB.</p>
                    </div>

                    <button type="submit" class="w-full bg-[#38B2AC] hover:bg-[#2C9A94] text-white px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all">
                        Guardar carta
                    </button>
                </form>

                <div class="lg:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @forelse($animal->vaccinationLetters as $letter)
                            <div class="border border-slate-200 rounded-2xl overflow-hidden bg-white shadow-sm">
                                <div class="aspect-[4/3] bg-slate-100">
                                    <img src="{{ route('client.vaccination-letters.show', $letter) }}" alt="Carta de vacunacion {{ $loop->iteration }}" class="w-full h-full object-cover">
                                </div>
                                <div class="p-4 flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Carta {{ $loop->iteration }}</p>
                                        <p class="text-sm font-black text-[#0F172A] mt-1">{{ $letter->date->format('d/m/Y') }}</p>
                                    </div>
                                    <a href="{{ route('client.vaccination-letters.print', $letter) }}" target="_blank" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                                        Imprimir
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="md:col-span-2 border border-dashed border-slate-200 rounded-2xl px-6 py-12 text-center">
                                <p class="text-sm font-black text-[#0F172A]">Sin cartas de vacunacion</p>
                                <p class="text-xs font-semibold text-slate-400 mt-2">Sube la primera imagen para este paciente.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div x-show="tab === 'videos'" class="p-6 space-y-6" x-cloak>
            <div class="overflow-hidden border border-slate-200 rounded-2xl bg-white">
                <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-black text-[#0F172A]">Videos clinicos</p>
                        <p class="text-[11px] text-slate-400 font-semibold mt-1">Registro compacto de visitas, evolucion y evidencia clinica.</p>
                    </div>

                    <button type="button" @click="videoFormOpen = true" class="inline-flex items-center justify-center rounded-xl bg-[#38B2AC] px-4 py-2.5 text-[10px] font-black uppercase tracking-[0.2em] text-white transition-all hover:bg-[#2C9A94]">
                        Agregar video
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="w-16 px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Ver</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Fecha</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Descripcion</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Archivo</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Accion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($animal->videos as $video)
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="px-4 py-3 text-center">
                                        <button type="button"
                                                title="Reproducir video"
                                                @click="playingVideoUrl = @js(route('client.animal-videos.show', $video)); playingVideoTitle = @js($video->original_name ?? 'Video clinico'); videoPlayerOpen = true"
                                                class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-red-50 text-red-600 ring-1 ring-red-100 transition-all hover:bg-red-600 hover:text-white">
                                            <svg viewBox="0 0 24 24" class="h-5 w-5 fill-current" aria-hidden="true">
                                                <path d="M8 5v14l11-7z"></path>
                                            </svg>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <p class="text-xs font-black text-[#0F172A]">{{ $video->video_date->format('d/m/Y') }}</p>
                                    </td>
                                    <td class="px-4 py-3 min-w-[260px]">
                                        <p class="text-xs font-semibold text-slate-600">
                                            {{ \Illuminate\Support\Str::limit($video->notes ?: 'Sin descripcion', 120) }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 min-w-[220px]">
                                        <p class="text-xs font-bold text-[#0F172A]">{{ \Illuminate\Support\Str::limit($video->original_name ?? 'Video', 42) }}</p>
                                        <p class="text-[11px] font-semibold text-slate-400 mt-0.5">
                                            {{ $video->mime_type ?? 'video/mp4' }}
                                            @if($video->size)
                                                &middot; {{ number_format($video->size / 1048576, 1) }} MB
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <form action="{{ route('client.animal-videos.destroy', $video) }}" method="POST" onsubmit="return confirm('Eliminar este video del expediente?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-3 py-2 rounded-lg bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest hover:bg-rose-100">
                                                Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-12 text-center">
                                        <p class="text-sm font-black text-[#0F172A]">Sin videos registrados</p>
                                        <p class="text-xs font-semibold text-slate-400 mt-2">Sube el primer video clinico de este paciente.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="videoFormOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[110] flex items-center justify-center bg-[#0F172A]/70 px-4 py-6 backdrop-blur-sm">
                <div @click.outside="if (!videoUploading) videoFormOpen = false" class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <p class="text-sm font-black text-[#0F172A]">Nuevo video</p>
                            <p class="text-[11px] text-slate-400 font-semibold mt-1">Agrega fecha, notas y archivo multimedia.</p>
                        </div>
                        <button type="button" @click="videoFormOpen = false" :disabled="videoUploading" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-500 hover:bg-slate-200 disabled:opacity-50">
                            x
                        </button>
                    </div>

                    <form action="{{ route('client.animals.videos.store', $animal) }}" method="POST" enctype="multipart/form-data" @submit="videoUploading = true; videoFormOpen = false" class="space-y-4 p-5">
                        @csrf

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Fecha del video *</label>
                            <input type="date" name="video_date" value="{{ old('video_date', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Notas</label>
                            <textarea name="notes" rows="5" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none resize-none" placeholder="Observaciones del veterinario...">{{ old('notes') }}</textarea>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Video *</label>
                            <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,video/x-matroska" required class="block w-full text-xs font-bold text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-[#0F172A] file:px-4 file:py-2.5 file:text-xs file:font-black file:uppercase file:tracking-widest file:text-white hover:file:bg-slate-800">
                            <p class="text-[10px] text-slate-400 font-semibold">Formatos: MP4, MOV, AVI, WEBM o MKV. Maximo 500 MB.</p>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="videoFormOpen = false" :disabled="videoUploading" class="px-4 py-3 rounded-xl bg-slate-100 text-slate-500 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-200 disabled:opacity-50">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="videoUploading" class="bg-[#38B2AC] hover:bg-[#2C9A94] text-white px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all disabled:opacity-60">
                                <span x-show="!videoUploading">Guardar video</span>
                                <span x-show="videoUploading" x-cloak>Procesando...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div x-show="videoPlayerOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[110] flex items-center justify-center bg-[#0F172A]/80 px-4 py-6 backdrop-blur-sm">
                <div @click.outside="videoPlayerOpen = false; playingVideoUrl = ''" class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
                        <p class="truncate text-sm font-black text-[#0F172A]" x-text="playingVideoTitle"></p>
                        <button type="button" @click="videoPlayerOpen = false; playingVideoUrl = ''" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-500 hover:bg-slate-200">
                            x
                        </button>
                    </div>
                    <div class="aspect-video bg-slate-950">
                        <template x-if="videoPlayerOpen">
                            <video controls autoplay class="h-full w-full" :src="playingVideoUrl"></video>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="tab === 'radiologia'" class="p-6 space-y-6" x-cloak>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-black text-[#0F172A]">Carpetas de radiologia</p>
                    <p class="text-[11px] text-slate-400 font-semibold mt-1">Cada carpeta agrupa las RX de un estudio o visita.</p>
                </div>
                <button type="button" @click="radiologyFormOpen = true" class="inline-flex items-center justify-center rounded-xl bg-[#38B2AC] px-4 py-2.5 text-[10px] font-black uppercase tracking-[0.2em] text-white transition-all hover:bg-[#2C9A94]">
                    Nueva carpeta
                </button>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($animal->radiologyStudies as $study)
                    <button type="button"
                            @click="radiologyStudyOpen = {{ $study->id }}; radiologyStudyTitle = @js($study->name); radiologyStudyDate = @js($study->study_date->format('d/m/Y')); radiologyStudyNotes = @js($study->notes ?? '')"
                            class="group relative min-h-[140px] overflow-hidden rounded-2xl border border-amber-200 bg-amber-50 p-5 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md">
                        <div class="absolute left-5 top-0 h-6 w-28 rounded-b-xl bg-amber-200/80"></div>
                        <div class="pt-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-[#0F172A]">{{ $study->name }}</p>
                                    <p class="mt-1 text-[11px] font-bold uppercase tracking-widest text-amber-700">{{ $study->study_date->format('d/m/Y') }}</p>
                                </div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-amber-700 ring-1 ring-amber-200">
                                    {{ $study->images->count() }} RX
                                </span>
                            </div>
                            <p class="mt-4 line-clamp-2 text-xs font-semibold text-slate-500">
                                {{ \Illuminate\Support\Str::limit($study->notes ?: 'Sin notas registradas.', 100) }}
                            </p>
                        </div>
                    </button>
                @empty
                    <div class="sm:col-span-2 xl:col-span-3 border border-dashed border-slate-200 rounded-2xl px-6 py-12 text-center">
                        <p class="text-sm font-black text-[#0F172A]">Sin carpetas de radiologia</p>
                        <p class="text-xs font-semibold text-slate-400 mt-2">Crea la primera carpeta para agrupar imagenes RX.</p>
                    </div>
                @endforelse
            </div>

            <div x-show="radiologyFormOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[110] flex items-center justify-center bg-[#0F172A]/70 px-4 py-6 backdrop-blur-sm">
                <div @click.outside="radiologyFormOpen = false" class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <p class="text-sm font-black text-[#0F172A]">Nueva carpeta RX</p>
                            <p class="text-[11px] text-slate-400 font-semibold mt-1">Crea la cabecera del estudio radiologico.</p>
                        </div>
                        <button type="button" @click="radiologyFormOpen = false" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-500 hover:bg-slate-200">x</button>
                    </div>

                    <form action="{{ route('client.animals.radiology-studies.store', $animal) }}" method="POST" class="space-y-4 p-5">
                        @csrf

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Nombre *</label>
                            <input type="text" name="name" value="{{ old('name') }}" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none" placeholder="Ej. RX torax lateral">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Fecha *</label>
                            <input type="date" name="study_date" value="{{ old('study_date', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Notas</label>
                            <textarea name="notes" rows="4" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none resize-none" placeholder="Notas generales del estudio...">{{ old('notes') }}</textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="radiologyFormOpen = false" class="px-4 py-3 rounded-xl bg-slate-100 text-slate-500 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-200">Cancelar</button>
                            <button type="submit" class="bg-[#38B2AC] hover:bg-[#2C9A94] text-white px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all">Crear carpeta</button>
                        </div>
                    </form>
                </div>
            </div>

            @foreach($animal->radiologyStudies as $study)
                <div x-show="radiologyStudyOpen === {{ $study->id }}" x-cloak x-transition.opacity class="fixed inset-0 z-[110] flex items-center justify-center bg-[#0F172A]/75 px-4 py-6 backdrop-blur-sm">
                    <div @click.outside="radiologyStudyOpen = null; radiologyImageUrl = ''" class="relative flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-[#0F172A]">{{ $study->name }}</p>
                                <p class="mt-1 text-[11px] font-bold uppercase tracking-widest text-slate-400">{{ $study->study_date->format('d/m/Y') }} &middot; {{ $study->images->count() }} RX</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="radiologyImageFormOpen = true" class="rounded-xl bg-[#38B2AC] px-4 py-2.5 text-[10px] font-black uppercase tracking-[0.2em] text-white hover:bg-[#2C9A94]">
                                    Agregar RX
                                </button>
                                <form action="{{ route('client.radiology-studies.destroy', $study) }}" method="POST" onsubmit="return confirm('Eliminar esta carpeta y todas sus RX?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-xl bg-rose-50 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-rose-600 hover:bg-rose-100">Eliminar</button>
                                </form>
                                <button type="button" @click="radiologyStudyOpen = null; radiologyImageUrl = ''" class="rounded-xl bg-slate-100 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-200">Cerrar</button>
                            </div>
                        </div>

                        <div class="overflow-y-auto p-5">
                            @if($study->notes)
                                <div class="mb-5 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Notas</p>
                                    <p class="mt-2 whitespace-pre-line text-sm font-semibold text-slate-600">{{ $study->notes }}</p>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                @forelse($study->images as $image)
                                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                        <button type="button" @click="radiologyImageUrl = @js(route('client.radiology-images.show', $image)); radiologyImageTitle = @js($image->label ?: $image->original_name ?: 'RX')" class="block aspect-[4/3] w-full bg-slate-100">
                                            <img src="{{ route('client.radiology-images.show', $image) }}" alt="{{ $image->label ?? $image->original_name ?? 'RX' }}" class="h-full w-full object-cover">
                                        </button>
                                        <div class="space-y-3 p-4">
                                            <div>
                                                <p class="text-xs font-black text-[#0F172A]">{{ $image->label ?: 'RX' }}</p>
                                                <p class="mt-1 text-[11px] font-semibold text-slate-400">
                                                    {{ \Illuminate\Support\Str::limit($image->original_name ?? 'Imagen', 34) }}
                                                    @if($image->size)
                                                        &middot; {{ number_format($image->size / 1048576, 1) }} MB
                                                    @endif
                                                </p>
                                            </div>
                                            @if($image->notes)
                                                <p class="line-clamp-2 text-xs font-semibold text-slate-500">{{ $image->notes }}</p>
                                            @endif
                                            <form action="{{ route('client.radiology-images.destroy', $image) }}" method="POST" onsubmit="return confirm('Eliminar esta RX?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg bg-rose-50 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-rose-600 hover:bg-rose-100">Eliminar RX</button>
                                            </form>
                                        </div>
                                    </div>
                                @empty
                                    <div class="md:col-span-2 xl:col-span-3 rounded-2xl border border-dashed border-slate-200 px-6 py-12 text-center">
                                        <p class="text-sm font-black text-[#0F172A]">Carpeta sin RX</p>
                                        <p class="mt-2 text-xs font-semibold text-slate-400">Agrega imagenes radiologicas a este estudio.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div x-show="radiologyImageFormOpen" x-cloak x-transition.opacity class="absolute inset-0 z-[111] flex items-start justify-center overflow-y-auto bg-[#0F172A]/70 px-4 py-6 backdrop-blur-sm">
                            <div @click.outside="radiologyImageFormOpen = false" class="w-full max-w-xl rounded-2xl bg-white shadow-2xl">
                                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                                    <div>
                                        <p class="text-sm font-black text-[#0F172A]">Agregar RX</p>
                                        <p class="text-[11px] text-slate-400 font-semibold mt-1">{{ $study->name }}</p>
                                    </div>
                                    <button type="button" @click="radiologyImageFormOpen = false" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-500 hover:bg-slate-200">x</button>
                                </div>

                                <form action="{{ route('client.radiology-studies.images.store', $study) }}" method="POST" enctype="multipart/form-data" @submit="radiologyUploading = true; radiologyImageFormOpen = false; radiologyStudyOpen = null" class="max-h-[72vh] space-y-4 overflow-y-auto p-5">
                                    @csrf
                                    <div class="space-y-2">
                                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Etiqueta</label>
                                        <input type="text" name="label" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none" placeholder="Ej. Lateral izquierda">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Notas</label>
                                        <textarea name="notes" rows="3" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none resize-none" placeholder="Notas de estas imagenes..."></textarea>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Imagenes RX *</label>
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <input type="file" name="images[]" accept="image/png,image/jpeg,image/webp" multiple required class="block min-w-0 flex-1 text-xs font-bold text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-[#0F172A] file:px-4 file:py-2.5 file:text-xs file:font-black file:uppercase file:tracking-widest file:text-white hover:file:bg-slate-800">
                                            <button type="submit" class="shrink-0 bg-[#38B2AC] hover:bg-[#2C9A94] text-white px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all">Guardar RX</button>
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-semibold">Formatos: JPG, PNG o WEBP. Maximo 20 MB por imagen.</p>
                                    </div>
                                    <div class="flex justify-end gap-3 pt-2">
                                        <button type="button" @click="radiologyImageFormOpen = false" class="px-4 py-3 rounded-xl bg-slate-100 text-slate-500 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-200">Cancelar</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div x-show="radiologyImageUrl" x-cloak x-transition.opacity class="absolute inset-0 z-[112] flex items-center justify-center bg-[#0F172A]/85 px-4 py-6 backdrop-blur-sm">
                            <div @click.outside="radiologyImageUrl = ''" class="flex max-h-full w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                                <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
                                    <p class="truncate text-sm font-black text-[#0F172A]" x-text="radiologyImageTitle"></p>
                                    <button type="button" @click="radiologyImageUrl = ''" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-500 hover:bg-slate-200">x</button>
                                </div>
                                <div class="overflow-auto bg-slate-950 p-4">
                                    <img :src="radiologyImageUrl" alt="RX" class="mx-auto max-h-[72vh] max-w-full object-contain">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div x-show="tab === 'extra'" class="p-6" x-cloak>
            <div class="border border-dashed border-slate-200 rounded-2xl px-6 py-12 text-center">
                <p class="text-sm font-black text-[#0F172A]">Mas informacion del paciente</p>
                <p class="text-xs font-semibold text-slate-400 mt-2">Este espacio queda listo para vacunas, archivos, signos vitales o campos clinicos extendidos.</p>
            </div>
        </div>

        <div x-show="tab === 'telemedicina'" class="p-6 space-y-6" x-cloak>
            @if(session('telemedicine_link'))
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5">
                    <p class="text-[10px] font-black text-emerald-700 uppercase tracking-widest">Link generado</p>
                    <input type="text" readonly value="{{ session('telemedicine_link') }}" class="mt-3 w-full bg-white border border-emerald-100 rounded-xl px-4 py-3 text-xs font-semibold text-[#0F172A]">
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <form action="{{ route('client.animals.telemedicine-shares.store', $animal) }}" method="POST" @submit="if (!selectedTenant) { $event.preventDefault(); return; }" class="lg:col-span-1 border border-slate-200 rounded-2xl p-5 space-y-4 bg-slate-50/40">
                    @csrf

                    <div>
                        <p class="text-sm font-black text-[#0F172A]">Compartir expediente</p>
                        <p class="text-[11px] text-slate-400 font-semibold mt-1">El tenant destino podra ver este expediente en modo lectura mientras el acceso este activo.</p>
                    </div>

                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <input type="checkbox" checked required class="rounded border-slate-300 text-[#38B2AC] focus:ring-[#38B2AC]">
                        <span class="text-xs font-black uppercase tracking-widest text-[#0F172A]">Activar compartir</span>
                    </label>

                    <div class="space-y-2 relative">
                        <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-widest">Tenant destino *</label>
                        <input type="hidden" name="shared_with_tenant_id" :value="selectedTenant ? selectedTenant.id : ''">
                        <input type="text"
                               x-model="tenantQuery"
                               @input.debounce.300ms="searchTenants()"
                               :disabled="selectedTenant !== null"
                               placeholder="Busca por nombre, slug o ID..."
                               class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 pr-20 text-sm font-semibold text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all outline-none disabled:bg-slate-50">

                        <template x-if="selectedTenant">
                            <button type="button" @click="removeTenant()" class="absolute right-3 top-8 rounded-lg bg-rose-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-rose-600">
                                Quitar
                            </button>
                        </template>

                        <div x-show="tenantResults.length > 0" x-cloak class="absolute z-30 mt-2 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                            <template x-for="tenant in tenantResults" :key="tenant.id">
                                <button type="button" @click="selectTenant(tenant)" class="block w-full px-4 py-3 text-left hover:bg-slate-50">
                                    <span class="block text-xs font-black text-[#0F172A]" x-text="tenant.label"></span>
                                    <span class="block text-[11px] font-semibold text-slate-400" x-text="tenant.business_name ? `${tenant.business_name} · ${tenant.slug}` : tenant.slug"></span>
                                </button>
                            </template>
                        </div>

                        <p x-show="tenantQuery.length > 0 && tenantQuery.length < 2 && !selectedTenant" x-cloak class="text-[11px] font-semibold text-slate-400">
                            Escribe al menos 2 caracteres para buscar.
                        </p>
                    </div>

                    <button type="submit" :disabled="!selectedTenant" class="w-full bg-[#0F172A] hover:bg-slate-800 text-white px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all disabled:opacity-40 disabled:pointer-events-none">
                        Generar enlace
                    </button>
                </form>

                <div class="lg:col-span-2 border border-slate-200 rounded-2xl overflow-hidden bg-white">
                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/60">
                        <p class="text-sm font-black text-[#0F172A]">Accesos activos</p>
                        <p class="text-[11px] text-slate-400 font-semibold mt-1">Revoca un acceso para invalidar su link inmediatamente.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-100">
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Tenant destino</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Ultimo acceso</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Link</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Accion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($animal->shares->sortByDesc('created_at') as $share)
                                    <tr>
                                        <td class="px-4 py-3 text-xs font-bold text-[#0F172A]">
                                            #{{ $share->shared_with_tenant_id }} {{ $share->sharedWithTenant?->name ? '- ' . $share->sharedWithTenant->name : '' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex text-[9px] font-black uppercase tracking-widest {{ $share->is_active ? 'text-emerald-700 bg-emerald-50' : 'text-slate-400 bg-slate-100' }} px-2.5 py-1 rounded-full">
                                                {{ $share->is_active ? 'Activo' : 'Revocado' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs font-semibold text-slate-500">{{ optional($share->last_accessed_at)->format('d/m/Y H:i') ?? '--' }}</td>
                                        <td class="px-4 py-3">
                                            @if($share->is_active)
                                                <input type="text" readonly value="{{ route('client.telemedicine.animals.show', $share->token) }}" class="w-64 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-[11px] font-semibold text-slate-500">
                                            @else
                                                <span class="text-[11px] font-semibold text-slate-300">Link inactivo</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @if($share->is_active)
                                                <form action="{{ route('client.telemedicine-shares.destroy', $share) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="px-3 py-2 rounded-lg bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest hover:bg-rose-100">
                                                        Revocar
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-[11px] font-semibold text-slate-300">--</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center text-xs font-bold text-slate-400">Este expediente todavia no se comparte con otros tenants.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
