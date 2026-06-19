@extends('layouts.client')

@section('title', 'Expediente del Paciente')
@section('contextual-tour', 'patient-record')

@section('content')
<div class="space-y-6" x-data="{
    tab: @js(session('animalTab', old('intent') ? 'reportes' : 'datos')),
    loading: false,
    microchipUploading: false,
    vaccinationFormOpen: @js($errors->has('date') || $errors->has('vaccine_name') || $errors->has('image')),
    vaccinationSaving: false,
    reportFormOpen: @js((bool) old('intent')),
    reportSaving: false,
    videoUploading: false,
    maxVideoUploadBytes: 100 * 1024 * 1024,
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
    validateVideoFile(event) {
        const file = event.target.files[0] ?? null;

        if (file && file.size > this.maxVideoUploadBytes) {
            alert('El video pesa mas de 100 MB. Sube un archivo mas ligero para evitar errores de carga.');
            event.target.value = '';
        }
    },
    validateVideoUpload(event) {
        const file = event.target.elements.video.files[0] ?? null;

        if (!file) {
            alert('Selecciona un video antes de guardar.');
            event.preventDefault();
            return;
        }

        if (file.size > this.maxVideoUploadBytes) {
            alert('El video pesa mas de 100 MB. Sube un archivo mas ligero para evitar errores de carga.');
            event.preventDefault();
            return;
        }

        this.videoUploading = true;
        this.videoFormOpen = false;
    },
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
    },
    submitAnimalData(event) {
        this.loading = true;
        this.microchipUploading = Boolean(event.target.elements.microchip_image?.files[0]);
    }
}">
    <div x-show="reportSaving" x-cloak x-transition.opacity class="fixed inset-0 z-[130] flex items-center justify-center theme-overlay px-4 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
            <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 theme-border-primary-soft theme-spinner-primary"></div>
            <p class="mt-4 text-sm font-black uppercase tracking-widest theme-text-heading">Procesando reporte</p>
            <p class="mt-2 text-xs font-semibold text-slate-500">Guardando contenido, optimizando imagenes y generando el PDF.</p>
        </div>
    </div>

    <div x-show="microchipUploading" x-cloak x-transition.opacity class="fixed inset-0 z-[120] flex items-center justify-center theme-overlay px-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="microchip-upload-title">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full theme-bg-primary-soft">
                <div class="h-8 w-8 animate-spin rounded-full border-4 theme-border-primary-soft theme-spinner-primary"></div>
            </div>
            <p id="microchip-upload-title" class="mt-4 text-sm font-black uppercase tracking-widest theme-text-heading">Guardando microchip</p>
            <p class="mt-2 text-xs font-semibold text-slate-500">Optimizando la foto y subiendola al expediente. No cierres esta ventana.</p>
        </div>
    </div>

    <div x-show="videoUploading" x-cloak x-transition.opacity class="fixed inset-0 z-[120] flex items-center justify-center theme-overlay px-4 backdrop-blur-sm">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full theme-bg-primary-soft">
                <div class="h-8 w-8 animate-spin rounded-full border-4 theme-border-primary-soft theme-spinner-primary"></div>
            </div>
            <p class="mt-4 text-sm font-black uppercase tracking-widest theme-text-heading">Procesando video</p>
            <p class="mt-2 text-xs font-semibold text-slate-500">Optimizando y guardando el archivo. Esto puede tardar unos minutos.</p>
        </div>
    </div>

    <div x-show="vaccinationSaving" x-cloak x-transition.opacity class="fixed inset-0 z-[120] flex items-center justify-center theme-overlay px-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="vaccination-saving-title">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full theme-bg-primary-soft">
                <div class="h-8 w-8 animate-spin rounded-full border-4 theme-border-primary-soft theme-spinner-primary"></div>
            </div>
            <p id="vaccination-saving-title" class="mt-4 text-sm font-black uppercase tracking-widest theme-text-heading">Guardando carta</p>
            <p class="mt-2 text-xs font-semibold text-slate-500">Subiendo la imagen, registrando la vacuna y generando el PDF.</p>
        </div>
    </div>

    <div x-show="radiologyUploading" x-cloak x-transition.opacity class="fixed inset-0 z-[120] flex items-center justify-center theme-overlay px-4 backdrop-blur-sm">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full theme-bg-primary-soft">
                <div class="h-8 w-8 animate-spin rounded-full border-4 theme-border-primary-soft theme-spinner-primary"></div>
            </div>
            <p class="mt-4 text-sm font-black uppercase tracking-widest theme-text-heading">Guardando radiologia</p>
            <p class="mt-2 text-xs font-semibold text-slate-500">Subiendo archivos RX al expediente.</p>
        </div>
    </div>

    <div class="fixed top-4 right-4 z-[99] space-y-3 min-w-[320px]">
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition class="bg-white border-l-4 border-emerald-500 rounded-xl shadow-xl p-4 flex items-center justify-between border border-slate-100">
                <div>
                    <p class="text-xs font-black theme-text-heading uppercase tracking-wider">Operacion Exitosa</p>
                    <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ session('success') }}</p>
                </div>
                <button @click="show = false" class="text-slate-400 hover:text-slate-600 text-xs ml-4">x</button>
            </div>
        @endif

        @if(session('error') || $errors->any())
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition class="bg-white border-l-4 border-red-500 rounded-xl shadow-xl p-4 border border-slate-100">
                <p class="text-xs font-black theme-text-heading uppercase tracking-wider">Revisa el formulario</p>
                <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ session('error') ?? 'Hay campos pendientes o con formato incorrecto.' }}</p>
            </div>
        @endif
    </div>

    <div data-tour="patient-record-header" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl theme-bg-primary-soft theme-text-primary flex items-center justify-center font-black text-xl">
                {{ substr($animal->name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-3xl font-black theme-text-heading tracking-tighter">{{ $animal->name }}</h1>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">
                    {{ $animal->animalType->name ?? 'Sin especie' }} · {{ $animal->customer->full_name ?? 'Sin propietario' }}
                </p>
            </div>
        </div>

        <a href="{{ route('client.animals.index') }}" class="inline-flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl font-bold text-xs tracking-wide transition-all">
            Volver a Pacientes
        </a>
    </div>

    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-6 pt-4">
            <nav class="flex flex-wrap gap-1">
                <button data-tour="patient-tab-details" type="button" @click="tab = 'datos'" :class="tab === 'datos' ? 'theme-border-primary theme-text-primary' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Datos del Paciente
                </button>
                <button data-tour="patient-tab-history" type="button" @click="tab = 'historial'" :class="tab === 'historial' ? 'theme-border-primary theme-text-primary' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Historial de Servicios
                </button>
                <button data-tour="patient-tab-vaccination" type="button" @click="tab = 'vacunacion'" :class="tab === 'vacunacion' ? 'theme-border-primary theme-text-primary' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Cartas de Vacunacion
                </button>
                <button data-tour="patient-tab-videos" type="button" @click="tab = 'videos'" :class="tab === 'videos' ? 'theme-border-primary theme-text-primary' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Videos
                </button>
                <button data-tour="patient-tab-radiology" type="button" @click="tab = 'radiologia'" :class="tab === 'radiologia' ? 'theme-border-primary theme-text-primary' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Radiologia
                </button>
                <button type="button" @click="tab = 'reportes'" :class="tab === 'reportes' ? 'theme-border-primary theme-text-primary' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Reportes
                </button>
                <button data-tour="patient-tab-telemedicine" type="button" @click="tab = 'telemedicina'" :class="tab === 'telemedicina' ? 'theme-border-primary theme-text-primary' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Telemedicina
                </button>
            </nav>
        </div>

        <div x-show="tab === 'datos'" class="p-6">
            <div class="mb-6 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Visibilidad en app</p>
                    <p class="mt-1 text-xs font-semibold text-slate-600">
                        @if(!$hasActivePortalAccess)
                            El cliente necesita acceso activo antes de mostrar este paciente.
                        @elseif($isVisibleInPortal)
                            Este paciente es visible para {{ $animal->customer?->full_name }}.
                        @else
                            Este paciente esta oculto en la app del cliente.
                        @endif
                    </p>
                </div>

                @if($hasActivePortalAccess)
                    <form action="{{ route('client.animals.portal-visibility.toggle', $animal) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-[10px] font-black uppercase tracking-widest transition-colors {{ $isVisibleInPortal ? 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100' : 'theme-button-primary' }}">
                            {{ $isVisibleInPortal ? 'Ocultar de la app' : 'Mostrar en la app' }}
                        </button>
                    </form>
                @elseif($animal->customer)
                    <a href="{{ route('client.customers.show', $animal->customer) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-[10px] font-black uppercase tracking-widest theme-text-heading hover:bg-slate-100">
                        Configurar acceso del cliente
                    </a>
                @endif
            </div>

            <form id="animal-data-form" action="{{ route('client.animals.update', $animal) }}" method="POST" enctype="multipart/form-data" @submit="submitAnimalData($event)" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Propietario *</label>
                        <select name="customer_id" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('customer_id', $animal->customer_id) == $customer->id)>{{ $customer->full_name }} ({{ $customer->phone }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Especie / Tipo *</label>
                        <select name="animal_type_id" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                            @foreach($animalTypes as $type)
                                <option value="{{ $type->id }}" @selected(old('animal_type_id', $animal->animal_type_id) == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Club</label>
                        <select name="club_id" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                            <option value="">Sin club</option>
                            @foreach($clubs as $club)
                                <option value="{{ $club->id }}" @selected(old('club_id', $animal->club_id) == $club->id)>{{ $club->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Nombre *</label>
                        <input type="text" name="name" value="{{ old('name', $animal->name) }}" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Sexo *</label>
                        <select name="sex" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                            <option value="male" @selected(old('sex', $animal->sex) === 'male')>Macho</option>
                            <option value="female" @selected(old('sex', $animal->sex) === 'female')>Hembra</option>
                            <option value="unknown" @selected(old('sex', $animal->sex) === 'unknown')>Desconocido</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Fecha de Nacimiento</label>
                        <input type="date" name="birthdate" value="{{ old('birthdate', optional($animal->birthdate)->format('Y-m-d')) }}" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Peso (kg)</label>
                        <input type="number" step="0.01" name="weight" value="{{ old('weight', $animal->weight) }}" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Color</label>
                        <input type="text" name="color" value="{{ old('color', $animal->color) }}" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Microchip</label>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <input type="text" name="microchip" value="{{ old('microchip', $animal->microchip) }}" class="min-w-0 flex-1 bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                            @if($animal->microchip_image_path)
                                <a href="{{ route('public.microchip-letters.print', $animal->microchip_print_token) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-xl theme-button-primary px-4 py-3 text-[10px] font-black uppercase tracking-widest whitespace-nowrap">
                                    Imprimir chip
                                </a>
                                <button type="submit" form="delete-microchip-image-form" onclick="return confirm('¿Eliminar la foto del microchip?')" class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-red-600 hover:bg-red-100">
                                    Eliminar
                                </button>
                            @else
                                <label class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-100 whitespace-nowrap">
                                    <span>Subir foto</span>
                                    <input type="file" name="microchip_image" accept="image/jpeg,image/png,image/webp" class="sr-only" onchange="this.previousElementSibling.textContent = this.files[0]?.name || 'Subir foto'">
                                </label>
                            @endif
                        </div>
                        @error('microchip_image')
                            <p class="text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Estado *</label>
                        <select name="status" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                            <option value="active" @selected(old('status', $animal->status) === 'active')>Activo</option>
                            <option value="inactive" @selected(old('status', $animal->status) === 'inactive')>Inactivo</option>
                            <option value="deceased" @selected(old('status', $animal->status) === 'deceased')>Fallecido</option>
                            <option value="transferred" @selected(old('status', $animal->status) === 'transferred')>Transferido</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Notas Clinicas / Alergias</label>
                    <textarea name="notes" rows="4" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none resize-none">{{ old('notes', $animal->notes) }}</textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('client.animals.index') }}" class="px-5 py-3 rounded-xl bg-slate-100 text-slate-500 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-200">Cancelar</a>
                    <button type="submit" :disabled="loading" class="theme-surface-dark px-6 py-3 rounded-xl text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 disabled:opacity-50">
                        Guardar Cambios
                    </button>
                </div>
            </form>
            @if($animal->microchip_image_path)
                <form id="delete-microchip-image-form" action="{{ route('client.animals.microchip-image.destroy', $animal) }}" method="POST" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
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
                            <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($serviceHistory as $detail)
                            @php
                                $note = $detail->note;
                                $tenant = auth()->user()->tenant;
                                $publicUrl = route('public.ventas.ticket', $note->public_token);
                                $whatsappMessage = "Hola " . $note->customer->name . ", adjunto el ticket de su visita en " . $tenant->name . ": " . $publicUrl;
                                $whatsappUrl = "https://wa.me/" . preg_replace('/[^0-9]/', '', $note->customer->phone) . "?text=" . urlencode($whatsappMessage);
                            @endphp
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-4 py-3 text-xs font-bold theme-text-heading">{{ optional($detail->note?->date_at)->format('d/m/Y') ?? '--' }}</td>
                                <td class="px-4 py-3 text-xs font-bold text-slate-500">{{ $detail->note->folio ?? 'Sin folio' }}</td>
                                <td class="px-4 py-3 text-xs font-bold theme-text-heading">{{ $detail->catalogItem->name ?? 'Concepto eliminado' }}</td>
                                <td class="px-4 py-3 text-xs font-bold text-right text-slate-500">{{ $detail->quantity }}</td>
                                <td class="px-4 py-3 text-xs font-black text-right theme-text-heading">${{ number_format($detail->subtotal, 2) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('client.ventas.show', $detail->note) }}" class="p-2 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors" title="Ver Detalles">
                                            🔍
                                        </a>
                                        @if($note->customer->phone)
                                            <a href="{{ $whatsappUrl }}" target="_blank" class="p-2 rounded-lg bg-emerald-100 text-emerald-600 hover:bg-emerald-200 transition-colors" title="Enviar por WhatsApp">
                                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-xs font-bold text-slate-400">Este paciente todavia no tiene servicios registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="tab === 'vacunacion'" class="p-6 space-y-6" x-cloak>
            <div class="overflow-hidden border border-slate-200 rounded-2xl bg-white">
                <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-black theme-text-heading">Cartas de vacunacion</p>
                        <p class="text-[11px] text-slate-400 font-semibold mt-1">Solo se conservan 2 cartas. Al subir una tercera se reemplaza la segunda.</p>
                    </div>

                    <button type="button" @click="vaccinationFormOpen = true" class="inline-flex h-10 w-10 items-center justify-center rounded-xl theme-button-primary text-lg font-black transition-all" title="Agregar carta">
                        +
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="w-20 px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Ver</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Carta</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Fecha</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Vacuna</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($animal->vaccinationLetters as $letter)
                                <tr class="hover:bg-slate-50/70 transition-colors" x-data="{ copied: false }">
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ route('client.vaccination-letters.show', $letter) }}" target="_blank" rel="noopener" class="mx-auto flex h-12 w-12 items-center justify-center overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200 transition-all hover:ring-slate-300" title="Ver imagen">
                                            <img src="{{ route('client.vaccination-letters.show', $letter) }}" alt="Carta de vacunacion {{ $loop->iteration }}" class="h-full w-full object-cover">
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <p class="text-xs font-black theme-text-heading">Carta {{ $loop->iteration }}</p>
                                        <p class="text-[11px] font-semibold text-slate-400 mt-0.5">{{ optional($letter->created_at)->format('d/m/Y H:i') }}</p>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <p class="text-xs font-black theme-text-heading">{{ $letter->date->format('d/m/Y') }}</p>
                                    </td>
                                    <td class="px-4 py-3 min-w-[220px]">
                                        <p class="text-xs font-semibold text-slate-600">{{ $letter->vaccine_name }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button"
                                                    @click="navigator.clipboard.writeText(@js(route('public.vaccination-letters.share', $letter->public_token))); copied = true; setTimeout(() => copied = false, 2000)"
                                                    :class="copied ? 'bg-emerald-500 text-white' : 'bg-[#25D366]/10 text-[#25D366] hover:bg-[#25D366] hover:text-white'"
                                                    class="flex items-center gap-1.5 rounded-xl p-2 text-[9px] font-black uppercase tracking-widest shadow-sm transition-all">
                                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                                <span x-text="copied ? 'Copiado' : 'Compartir'"></span>
                                            </button>
                                            <a href="{{ route('client.vaccination-letters.print', $letter) }}" target="_blank" rel="noopener" class="rounded-xl bg-slate-100 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600 transition-all hover:bg-slate-200">
                                                Imprimir
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-12 text-center">
                                        <p class="text-sm font-black theme-text-heading">Sin cartas de vacunacion</p>
                                        <p class="text-xs font-semibold text-slate-400 mt-2">Sube la primera imagen para este paciente.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="vaccinationFormOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[110] flex items-center justify-center theme-overlay px-4 py-6 backdrop-blur-sm" @keydown.escape.window="if (!vaccinationSaving) vaccinationFormOpen = false">
                <div @click.outside="if (!vaccinationSaving) vaccinationFormOpen = false" class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <p class="text-sm font-black theme-text-heading">Nueva carta</p>
                            <p class="text-[11px] text-slate-400 font-semibold mt-1">Agrega fecha, vacuna e imagen del documento.</p>
                        </div>
                        <button type="button" @click="vaccinationFormOpen = false" :disabled="vaccinationSaving" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-500 hover:bg-slate-200 disabled:opacity-50">
                            x
                        </button>
                    </div>

                    <form action="{{ route('client.animals.vaccination-letters.store', $animal) }}" method="POST" enctype="multipart/form-data" @submit="vaccinationSaving = true; vaccinationFormOpen = false" class="space-y-4 p-5">
                        @csrf

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Fecha *</label>
                            <input type="date" name="date" value="{{ old('date') }}" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                            @error('date')
                                <p class="text-[11px] font-semibold text-rose-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Vacuna *</label>
                            <input type="text" name="vaccine_name" value="{{ old('vaccine_name') }}" required maxlength="255" placeholder="Ej. Influenza equina" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                            @error('vaccine_name')
                                <p class="text-[11px] font-semibold text-rose-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Imagen *</label>
                            <input type="file" name="image" accept="image/png,image/jpeg,image/webp" required class="block w-full text-xs font-bold text-slate-500 file:mr-3 file:rounded-xl file:border-0 theme-file-input file:px-4 file:py-2.5 file:text-xs file:font-black file:uppercase file:tracking-widest file:text-white">
                            <p class="text-[10px] text-slate-400 font-semibold">Formatos: JPG, PNG o WEBP. Maximo 5 MB.</p>
                            @error('image')
                                <p class="text-[11px] font-semibold text-rose-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="vaccinationFormOpen = false" :disabled="vaccinationSaving" class="px-4 py-3 rounded-xl bg-slate-100 text-slate-500 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-200 disabled:opacity-50">Cancelar</button>
                            <button type="submit" :disabled="vaccinationSaving" class="theme-button-primary px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all disabled:opacity-60">
                                Guardar carta
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Legacy vaccination card layout replaced by the table/modal view above.
        <div x-show="false && tab === 'vacunacion'" class="p-6 space-y-6" x-cloak>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <form action="{{ route('client.animals.vaccination-letters.store', $animal) }}" method="POST" enctype="multipart/form-data" class="lg:col-span-1 border border-slate-200 rounded-2xl p-5 space-y-4 bg-slate-50/40">
                    @csrf

                    <div>
                        <p class="text-sm font-black theme-text-heading">Nueva carta</p>
                        <p class="text-[11px] text-slate-400 font-semibold mt-1">Solo se conservan 2 cartas. Al subir una tercera se reemplaza la segunda.</p>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Fecha *</label>
                        <input type="date" name="date" value="{{ old('date') }}" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Vacuna *</label>
                        <input type="text" name="vaccine_name" value="{{ old('vaccine_name') }}" required maxlength="255" placeholder="Ej. Influenza equina" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Imagen *</label>
                        <input type="file" name="image" accept="image/png,image/jpeg,image/webp" required class="block w-full text-xs font-bold text-slate-500 file:mr-3 file:rounded-xl file:border-0 theme-file-input file:px-4 file:py-2.5 file:text-xs file:font-black file:uppercase file:tracking-widest file:text-white">
                        <p class="text-[10px] text-slate-400 font-semibold">Formatos: JPG, PNG o WEBP. Maximo 5 MB.</p>
                    </div>

                    <button type="submit" class="w-full theme-button-primary px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all">
                        Guardar carta
                    </button>
                </form>

                <div class="lg:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @forelse($animal->vaccinationLetters as $letter)
                            <div class="border border-slate-200 rounded-2xl overflow-hidden bg-white shadow-sm" x-data="{ copied: false }">
                                <div class="aspect-[4/3] bg-slate-100">
                                    <img src="{{ route('client.vaccination-letters.show', $letter) }}" alt="Carta de vacunacion {{ $loop->iteration }}" class="w-full h-full object-cover">
                                </div>
                                <div class="p-4 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Carta {{ $loop->iteration }}</p>
                                        <p class="text-sm font-black theme-text-heading mt-1">{{ $letter->date->format('d/m/Y') }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" 
                                                @click="navigator.clipboard.writeText(@js(route('public.vaccination-letters.share', $letter->public_token))); copied = true; setTimeout(() => copied = false, 2000)"
                                                :class="copied ? 'bg-emerald-500 text-white' : 'bg-[#25D366]/10 text-[#25D366] hover:bg-[#25D366] hover:text-white'"
                                                class="p-2 rounded-xl text-[9px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 shadow-sm">
                                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                            <span x-text="copied ? '¡Copiado!' : 'Compartir'"></span>
                                        </button>
                                        <a href="{{ route('client.vaccination-letters.print', $letter) }}" target="_blank" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                                            Imprimir
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="md:col-span-2 border border-dashed border-slate-200 rounded-2xl px-6 py-12 text-center">
                                <p class="text-sm font-black theme-text-heading">Sin cartas de vacunacion</p>
                                <p class="text-xs font-semibold text-slate-400 mt-2">Sube la primera imagen para este paciente.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        --}}

        <div x-show="tab === 'videos'" class="p-6 space-y-6" x-cloak>
            <div class="overflow-hidden border border-slate-200 rounded-2xl bg-white">
                <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-black theme-text-heading">Videos clinicos</p>
                        <p class="text-[11px] text-slate-400 font-semibold mt-1">Registro compacto de visitas, evolucion y evidencia clinica.</p>
                    </div>

                    <button type="button" @click="videoFormOpen = true" class="inline-flex items-center justify-center rounded-xl theme-button-primary px-4 py-2.5 text-[10px] font-black uppercase tracking-[0.2em] transition-all">
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
                                        <p class="text-xs font-black theme-text-heading">{{ $video->video_date->format('d/m/Y') }}</p>
                                    </td>
                                    <td class="px-4 py-3 min-w-[260px]">
                                        <p class="text-xs font-semibold text-slate-600">
                                            {{ \Illuminate\Support\Str::limit($video->notes ?: 'Sin descripcion', 120) }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 min-w-[220px]">
                                        <p class="text-xs font-bold theme-text-heading">{{ \Illuminate\Support\Str::limit($video->original_name ?? 'Video', 42) }}</p>
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
                                        <p class="text-sm font-black theme-text-heading">Sin videos registrados</p>
                                        <p class="text-xs font-semibold text-slate-400 mt-2">Sube el primer video clinico de este paciente.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="videoFormOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[110] flex items-center justify-center theme-overlay px-4 py-6 backdrop-blur-sm">
                <div @click.outside="if (!videoUploading) videoFormOpen = false" class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <p class="text-sm font-black theme-text-heading">Nuevo video</p>
                            <p class="text-[11px] text-slate-400 font-semibold mt-1">Agrega fecha, notas y archivo multimedia.</p>
                        </div>
                        <button type="button" @click="videoFormOpen = false" :disabled="videoUploading" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-500 hover:bg-slate-200 disabled:opacity-50">
                            x
                        </button>
                    </div>

                    <form action="{{ route('client.animals.videos.store', $animal) }}" method="POST" enctype="multipart/form-data" @submit="validateVideoUpload($event)" class="space-y-4 p-5">
                        @csrf

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Fecha del video *</label>
                            <input type="date" name="video_date" value="{{ old('video_date', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Notas</label>
                            <textarea name="notes" rows="5" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none resize-none" placeholder="Observaciones del veterinario...">{{ old('notes') }}</textarea>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Video *</label>
                            <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,video/x-matroska" required @change="validateVideoFile($event)" class="block w-full text-xs font-bold text-slate-500 file:mr-3 file:rounded-xl file:border-0 theme-file-input file:px-4 file:py-2.5 file:text-xs file:font-black file:uppercase file:tracking-widest file:text-white">
                            <p class="text-[10px] text-slate-400 font-semibold">Formatos: MP4, MOV, AVI, WEBM o MKV. Maximo 100 MB.</p>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="videoFormOpen = false" :disabled="videoUploading" class="px-4 py-3 rounded-xl bg-slate-100 text-slate-500 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-200 disabled:opacity-50">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="videoUploading" class="theme-button-primary px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all disabled:opacity-60">
                                <span x-show="!videoUploading">Guardar video</span>
                                <span x-show="videoUploading" x-cloak>Procesando...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div x-show="videoPlayerOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[110] flex items-center justify-center theme-overlay px-4 py-6 backdrop-blur-sm">
                <div @click.outside="videoPlayerOpen = false; playingVideoUrl = ''" class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
                        <p class="truncate text-sm font-black theme-text-heading" x-text="playingVideoTitle"></p>
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
                    <p class="text-sm font-black theme-text-heading">Carpetas de radiologia</p>
                    <p class="text-[11px] text-slate-400 font-semibold mt-1">Cada carpeta agrupa las RX de un estudio o visita.</p>
                </div>
                <button type="button" @click="radiologyFormOpen = true" class="inline-flex items-center justify-center rounded-xl theme-button-primary px-4 py-2.5 text-[10px] font-black uppercase tracking-[0.2em] transition-all">
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
                                    <p class="truncate text-sm font-black theme-text-heading">{{ $study->name }}</p>
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
                        <p class="text-sm font-black theme-text-heading">Sin carpetas de radiologia</p>
                        <p class="text-xs font-semibold text-slate-400 mt-2">Crea la primera carpeta para agrupar imagenes RX.</p>
                    </div>
                @endforelse
            </div>

            <div x-show="radiologyFormOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[110] flex items-center justify-center theme-overlay px-4 py-6 backdrop-blur-sm">
                <div @click.outside="radiologyFormOpen = false" class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <p class="text-sm font-black theme-text-heading">Nueva carpeta RX</p>
                            <p class="text-[11px] text-slate-400 font-semibold mt-1">Crea la cabecera del estudio radiologico.</p>
                        </div>
                        <button type="button" @click="radiologyFormOpen = false" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-500 hover:bg-slate-200">x</button>
                    </div>

                    <form action="{{ route('client.animals.radiology-studies.store', $animal) }}" method="POST" class="space-y-4 p-5">
                        @csrf

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Nombre *</label>
                            <input type="text" name="name" value="{{ old('name') }}" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none" placeholder="Ej. RX torax lateral">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Fecha *</label>
                            <input type="date" name="study_date" value="{{ old('study_date', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Notas</label>
                            <textarea name="notes" rows="4" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none resize-none" placeholder="Notas generales del estudio...">{{ old('notes') }}</textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="radiologyFormOpen = false" class="px-4 py-3 rounded-xl bg-slate-100 text-slate-500 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-200">Cancelar</button>
                            <button type="submit" class="theme-button-primary px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all">Crear carpeta</button>
                        </div>
                    </form>
                </div>
            </div>

            @foreach($animal->radiologyStudies as $study)
                <div x-show="radiologyStudyOpen === {{ $study->id }}" x-cloak x-transition.opacity class="fixed inset-0 z-[110] flex items-center justify-center theme-overlay px-4 py-6 backdrop-blur-sm">
                    <div @click.outside="radiologyStudyOpen = null; radiologyImageUrl = ''" class="relative flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black theme-text-heading">{{ $study->name }}</p>
                                <p class="mt-1 text-[11px] font-bold uppercase tracking-widest text-slate-400">{{ $study->study_date->format('d/m/Y') }} &middot; {{ $study->images->count() }} RX</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="radiologyImageFormOpen = true" class="rounded-xl theme-button-primary px-4 py-2.5 text-[10px] font-black uppercase tracking-[0.2em]">
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

                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                                @forelse($study->images as $image)
                                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                                        <button type="button" @click="radiologyImageUrl = @js(route('client.radiology-images.show', $image)); radiologyImageTitle = @js($image->label ?: $image->original_name ?: 'RX')" class="block aspect-square w-full bg-slate-100">
                                            <img src="{{ route('client.radiology-images.show', $image) }}" alt="{{ $image->label ?? $image->original_name ?? 'RX' }}" class="h-full w-full object-cover">
                                        </button>
                                        <div class="space-y-2 p-3">
                                            <div>
                                                <p class="truncate text-xs font-black theme-text-heading">{{ $image->label ?: 'RX' }}</p>
                                                <p class="mt-1 text-[11px] font-semibold text-slate-400">
                                                    {{ \Illuminate\Support\Str::limit($image->original_name ?? 'Imagen', 24) }}
                                                    @if($image->size)
                                                        &middot; {{ number_format($image->size / 1048576, 1) }} MB
                                                    @endif
                                                </p>
                                            </div>
                                            @if($image->notes)
                                                <p class="line-clamp-2 text-[11px] font-semibold text-slate-500">{{ $image->notes }}</p>
                                            @endif
                                            <form action="{{ route('client.radiology-images.destroy', $image) }}" method="POST" onsubmit="return confirm('Eliminar esta RX?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg bg-rose-50 px-2.5 py-2 text-[9px] font-black uppercase tracking-widest text-rose-600 hover:bg-rose-100">Eliminar RX</button>
                                            </form>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-span-full rounded-2xl border border-dashed border-slate-200 px-6 py-12 text-center">
                                        <p class="text-sm font-black theme-text-heading">Carpeta sin RX</p>
                                        <p class="mt-2 text-xs font-semibold text-slate-400">Agrega imagenes radiologicas a este estudio.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div x-show="radiologyImageFormOpen" x-cloak x-transition.opacity class="absolute inset-0 z-[111] flex items-start justify-center overflow-y-auto theme-overlay px-4 py-6 backdrop-blur-sm">
                            <div @click.outside="radiologyImageFormOpen = false" class="w-full max-w-xl rounded-2xl bg-white shadow-2xl">
                                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                                    <div>
                                        <p class="text-sm font-black theme-text-heading">Agregar RX</p>
                                        <p class="text-[11px] text-slate-400 font-semibold mt-1">{{ $study->name }}</p>
                                    </div>
                                    <button type="button" @click="radiologyImageFormOpen = false" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-500 hover:bg-slate-200">x</button>
                                </div>

                                <form action="{{ route('client.radiology-studies.images.store', $study) }}" method="POST" enctype="multipart/form-data" @submit="radiologyUploading = true; radiologyImageFormOpen = false; radiologyStudyOpen = null" class="max-h-[72vh] space-y-4 overflow-y-auto p-5">
                                    @csrf
                                    <div class="space-y-2">
                                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Etiqueta</label>
                                        <input type="text" name="label" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none" placeholder="Ej. Lateral izquierda">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Notas</label>
                                        <textarea name="notes" rows="3" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none resize-none" placeholder="Notas de estas imagenes..."></textarea>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Imagenes RX *</label>
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <input type="file" name="images[]" accept="image/png,image/jpeg,image/webp" multiple required class="block min-w-0 flex-1 text-xs font-bold text-slate-500 file:mr-3 file:rounded-xl file:border-0 theme-file-input file:px-4 file:py-2.5 file:text-xs file:font-black file:uppercase file:tracking-widest file:text-white">
                                            <button type="submit" class="shrink-0 theme-button-primary px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all">Guardar RX</button>
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-semibold">Formatos: JPG, PNG o WEBP. Maximo 20 MB por imagen.</p>
                                    </div>
                                    <div class="flex justify-end gap-3 pt-2">
                                        <button type="button" @click="radiologyImageFormOpen = false" class="px-4 py-3 rounded-xl bg-slate-100 text-slate-500 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-200">Cancelar</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div x-show="radiologyImageUrl" x-cloak x-transition.opacity class="absolute inset-0 z-[112] flex items-center justify-center theme-overlay px-4 py-6 backdrop-blur-sm">
                            <div @click.outside="radiologyImageUrl = ''" class="flex max-h-full w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                                <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
                                    <p class="truncate text-sm font-black theme-text-heading" x-text="radiologyImageTitle"></p>
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

        <div x-show="tab === 'reportes'" class="p-6 space-y-5" x-cloak>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-black theme-text-heading">Reportes clinicos</p>
                    <p class="mt-1 text-[11px] font-semibold text-slate-400">Partes medicos, hallazgos e imagenes del paciente.</p>
                </div>
                <button type="button" @click="reportFormOpen = true" class="rounded-xl theme-button-primary px-5 py-3 text-[10px] font-black uppercase tracking-[0.2em]">
                    Nuevo reporte
                </button>
            </div>

            <div class="space-y-3">
                @forelse($animal->reports as $report)
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-black theme-text-heading">{{ $report->title }}</h3>
                                    <span class="rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-widest {{ $report->isDraft() ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                                        {{ $report->isDraft() ? 'Borrador' : 'Finalizado' }}
                                    </span>
                                </div>
                                <p class="mt-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    {{ $report->report_date->format('d/m/Y') }} &middot; {{ $report->author?->name ?? 'Veterinario' }} &middot; {{ $report->images->count() }} imagenes
                                </p>
                                <p class="mt-3 text-xs font-semibold leading-5 text-slate-600">{{ \Illuminate\Support\Str::limit(trim(strip_tags($report->content_html)), 220) }}</p>
                            </div>
                            <div x-data="{ copied: false }" class="flex shrink-0 flex-wrap gap-2">
                                @if($report->isDraft())
                                    <a href="{{ route('client.animal-reports.edit', $report) }}" class="rounded-xl bg-slate-100 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-200">Editar</a>
                                    <form action="{{ route('client.animal-reports.destroy', $report) }}" method="POST" onsubmit="return confirm('Eliminar este borrador?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-xl bg-rose-50 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-rose-600 hover:bg-rose-100">Eliminar</button>
                                    </form>
                                @else
                                    @php($publicReportUrl = route('public.animal-reports.pdf', $report->public_token))
                                    <button type="button"
                                            @click="navigator.clipboard.writeText(@js($publicReportUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                                            :class="copied ? 'bg-emerald-500 text-white' : 'bg-[#25D366]/10 text-[#25D366] hover:bg-[#25D366] hover:text-white'"
                                            class="flex items-center gap-1.5 rounded-xl p-2 text-[9px] font-black uppercase tracking-widest shadow-sm transition-all">
                                        <svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                        <span x-text="copied ? '¡Copiado!' : 'Compartir'"></span>
                                    </button>
                                    <a href="{{ route('client.animal-reports.pdf', $report) }}" target="_blank" rel="noopener" class="rounded-xl bg-slate-100 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600 transition-all hover:bg-slate-200">Abrir PDF</a>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 px-6 py-12 text-center">
                        <p class="text-sm font-black theme-text-heading">Sin reportes clinicos</p>
                        <p class="mt-2 text-xs font-semibold text-slate-400">Crea el primer parte medico de este paciente.</p>
                    </div>
                @endforelse
            </div>

            <div x-show="reportFormOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[115] flex items-start justify-center overflow-y-auto theme-overlay px-4 py-6 backdrop-blur-sm">
                <div @click.outside="reportFormOpen = false" class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <div>
                            <p class="text-sm font-black theme-text-heading">Nuevo reporte clinico</p>
                            <p class="mt-1 text-[11px] font-semibold text-slate-400">{{ $animal->name }} &middot; {{ $animal->customer?->full_name }}</p>
                        </div>
                        <button type="button" @click="reportFormOpen = false" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-500 hover:bg-slate-200">x</button>
                    </div>
                    <div class="max-h-[82vh] overflow-y-auto p-6">
                        @include('client.animals.reports.form', ['animal' => $animal, 'report' => null])
                    </div>
                </div>
            </div>
        </div>

        <div x-show="tab === 'telemedicina'" class="p-6 space-y-6" x-cloak>
            @if(session('telemedicine_link'))
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5">
                    <p class="text-[10px] font-black text-emerald-700 uppercase tracking-widest">Link generado</p>
                    <input type="text" readonly value="{{ session('telemedicine_link') }}" class="mt-3 w-full bg-white border border-emerald-100 rounded-xl px-4 py-3 text-xs font-semibold theme-text-heading">
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <form action="{{ route('client.animals.telemedicine-shares.store', $animal) }}" method="POST" @submit="if (!selectedTenant) { $event.preventDefault(); return; }" class="lg:col-span-1 border border-slate-200 rounded-2xl p-5 space-y-4 bg-slate-50/40">
                    @csrf

                    <div>
                        <p class="text-sm font-black theme-text-heading">Compartir expediente</p>
                        <p class="text-[11px] text-slate-400 font-semibold mt-1">El tenant destino podra ver este expediente en modo lectura mientras el acceso este activo.</p>
                    </div>

                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <input type="checkbox" checked required class="rounded border-slate-300 theme-text-primary theme-focus-ring-primary">
                        <span class="text-xs font-black uppercase tracking-widest theme-text-heading">Activar compartir</span>
                    </label>

                    <div class="space-y-2 relative">
                        <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Tenant destino *</label>
                        <input type="hidden" name="shared_with_tenant_id" :value="selectedTenant ? selectedTenant.id : ''">
                        <input type="text"
                               x-model="tenantQuery"
                               @input.debounce.300ms="searchTenants()"
                               :disabled="selectedTenant !== null"
                               placeholder="Busca por nombre, slug o ID..."
                               class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 pr-20 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none disabled:bg-slate-50">

                        <template x-if="selectedTenant">
                            <button type="button" @click="removeTenant()" class="absolute right-3 top-8 rounded-lg bg-rose-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-rose-600">
                                Quitar
                            </button>
                        </template>

                        <div x-show="tenantResults.length > 0" x-cloak class="absolute z-30 mt-2 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                            <template x-for="tenant in tenantResults" :key="tenant.id">
                                <button type="button" @click="selectTenant(tenant)" class="block w-full px-4 py-3 text-left hover:bg-slate-50">
                                    <span class="block text-xs font-black theme-text-heading" x-text="tenant.label"></span>
                                    <span class="block text-[11px] font-semibold text-slate-400" x-text="tenant.business_name ? `${tenant.business_name} · ${tenant.slug}` : tenant.slug"></span>
                                </button>
                            </template>
                        </div>

                        <p x-show="tenantQuery.length > 0 && tenantQuery.length < 2 && !selectedTenant" x-cloak class="text-[11px] font-semibold text-slate-400">
                            Escribe al menos 2 caracteres para buscar.
                        </p>
                    </div>

                    <button type="submit" :disabled="!selectedTenant" class="w-full theme-button-dark px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all disabled:opacity-40 disabled:pointer-events-none">
                        Generar enlace
                    </button>
                </form>

                <div class="lg:col-span-2 border border-slate-200 rounded-2xl overflow-hidden bg-white">
                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/60">
                        <p class="text-sm font-black theme-text-heading">Accesos activos</p>
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
                                        <td class="px-4 py-3 text-xs font-bold theme-text-heading">
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
