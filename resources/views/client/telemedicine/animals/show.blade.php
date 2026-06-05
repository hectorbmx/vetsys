@extends('layouts.client')

@section('title', 'Expediente Compartido')

@section('content')
<div class="space-y-6" x-data="{
    tab: 'datos',
    videoPlayerOpen: false,
    playingVideoUrl: '',
    playingVideoTitle: '',
    radiologyStudyOpen: null,
    radiologyImageUrl: '',
    radiologyImageTitle: ''
}">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-[#38B2AC]/10 text-[#38B2AC] flex items-center justify-center font-black text-xl">
                {{ substr($animal->name, 0, 1) }}
            </div>
            <div>
                <p class="text-[10px] font-black text-[#38B2AC] uppercase tracking-[0.24em]">Telemedicina</p>
                <h1 class="text-3xl font-black text-[#0F172A] tracking-tighter">{{ $animal->name }}</h1>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">
                    {{ $animal->animalType->name ?? 'Sin especie' }} · Expediente compartido por {{ $animal->tenant->name ?? 'tenant origen' }}
                </p>
            </div>
        </div>

        <span class="inline-flex items-center justify-center bg-emerald-50 text-emerald-700 px-4 py-2.5 rounded-xl font-black text-[10px] uppercase tracking-widest">
            Solo lectura
        </span>
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
            </nav>
        </div>

        <div x-show="tab === 'datos'" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                @foreach([
                    'Propietario' => $animal->customer->full_name ?? 'Sin propietario',
                    'Especie / Tipo' => $animal->animalType->name ?? 'Sin especie',
                    'Club' => $animal->club->name ?? 'Sin club',
                    'Nombre' => $animal->name,
                    'Sexo' => ['male' => 'Macho', 'female' => 'Hembra', 'unknown' => 'Desconocido'][$animal->sex] ?? 'Desconocido',
                    'Fecha de Nacimiento' => optional($animal->birthdate)->format('d/m/Y') ?? '--',
                    'Peso (kg)' => $animal->weight ?? '--',
                    'Color' => $animal->color ?? '--',
                    'Microchip' => $animal->microchip ?? '--',
                    'Estado' => ucfirst($animal->status),
                ] as $label => $value)
                    <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ $label }}</p>
                        <p class="mt-2 text-sm font-black text-[#0F172A]">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Notas Clinicas / Alergias</p>
                <p class="mt-2 text-sm font-semibold text-[#0F172A] whitespace-pre-line">{{ $animal->notes ?: 'Sin notas registradas.' }}</p>
            </div>
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
                            <tr>
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

        <div x-show="tab === 'vacunacion'" class="p-6" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($animal->vaccinationLetters as $letter)
                    <div class="border border-slate-200 rounded-2xl overflow-hidden bg-white shadow-sm">
                        <div class="aspect-[4/3] bg-slate-100">
                            <img src="{{ route('client.telemedicine.vaccination-letters.show', [$share->token, $letter]) }}" alt="Carta de vacunacion {{ $loop->iteration }}" class="w-full h-full object-cover">
                        </div>
                        <div class="p-4">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Carta {{ $loop->iteration }}</p>
                            <p class="text-sm font-black text-[#0F172A] mt-1">{{ $letter->date->format('d/m/Y') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="md:col-span-2 border border-dashed border-slate-200 rounded-2xl px-6 py-12 text-center">
                        <p class="text-sm font-black text-[#0F172A]">Sin cartas de vacunacion</p>
                        <p class="text-xs font-semibold text-slate-400 mt-2">No hay documentos visibles para este paciente.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div x-show="tab === 'videos'" class="p-6" x-cloak>
            <div class="overflow-hidden border border-slate-200 rounded-2xl bg-white">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/60">
                    <p class="text-sm font-black text-[#0F172A]">Videos clinicos</p>
                    <p class="text-[11px] text-slate-400 font-semibold mt-1">Contenido compartido en modo lectura.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="w-16 px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Ver</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Fecha</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Descripcion</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Archivo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($animal->videos as $video)
                                <tr>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button"
                                                @click="playingVideoUrl = @js(route('client.telemedicine.animal-videos.show', [$share->token, $video])); playingVideoTitle = @js($video->original_name ?? 'Video clinico'); videoPlayerOpen = true"
                                                class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-red-50 text-red-600 ring-1 ring-red-100 transition-all hover:bg-red-600 hover:text-white">
                                            <svg viewBox="0 0 24 24" class="h-5 w-5 fill-current" aria-hidden="true">
                                                <path d="M8 5v14l11-7z"></path>
                                            </svg>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-xs font-black text-[#0F172A] whitespace-nowrap">{{ $video->video_date->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 min-w-[260px] text-xs font-semibold text-slate-600">{{ \Illuminate\Support\Str::limit($video->notes ?: 'Sin descripcion', 120) }}</td>
                                    <td class="px-4 py-3 min-w-[220px]">
                                        <p class="text-xs font-bold text-[#0F172A]">{{ \Illuminate\Support\Str::limit($video->original_name ?? 'Video', 42) }}</p>
                                        <p class="text-[11px] font-semibold text-slate-400 mt-0.5">
                                            {{ $video->mime_type ?? 'video/mp4' }}
                                            @if($video->size)
                                                &middot; {{ number_format($video->size / 1048576, 1) }} MB
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-xs font-bold text-slate-400">Este paciente no tiene videos compartidos.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="videoPlayerOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[110] flex items-center justify-center bg-[#0F172A]/80 px-4 py-6 backdrop-blur-sm">
                <div @click.outside="videoPlayerOpen = false; playingVideoUrl = ''" class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
                        <p class="truncate text-sm font-black text-[#0F172A]" x-text="playingVideoTitle"></p>
                        <button type="button" @click="videoPlayerOpen = false; playingVideoUrl = ''" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-500 hover:bg-slate-200">x</button>
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
            <div>
                <p class="text-sm font-black text-[#0F172A]">Carpetas de radiologia</p>
                <p class="text-[11px] text-slate-400 font-semibold mt-1">Estudios RX compartidos en modo lectura.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($animal->radiologyStudies as $study)
                    <button type="button"
                            @click="radiologyStudyOpen = {{ $study->id }}"
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
                        <p class="text-xs font-semibold text-slate-400 mt-2">No hay estudios RX visibles para este paciente.</p>
                    </div>
                @endforelse
            </div>

            @foreach($animal->radiologyStudies as $study)
                <div x-show="radiologyStudyOpen === {{ $study->id }}" x-cloak x-transition.opacity class="fixed inset-0 z-[110] flex items-center justify-center bg-[#0F172A]/75 px-4 py-6 backdrop-blur-sm">
                    <div @click.outside="radiologyStudyOpen = null; radiologyImageUrl = ''" class="relative flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-[#0F172A]">{{ $study->name }}</p>
                                <p class="mt-1 text-[11px] font-bold uppercase tracking-widest text-slate-400">{{ $study->study_date->format('d/m/Y') }} &middot; {{ $study->images->count() }} RX</p>
                            </div>
                            <button type="button" @click="radiologyStudyOpen = null; radiologyImageUrl = ''" class="rounded-xl bg-slate-100 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-200">Cerrar</button>
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
                                    <button type="button"
                                            @click="radiologyImageUrl = @js(route('client.telemedicine.radiology-images.show', [$share->token, $image])); radiologyImageTitle = @js($image->label ?: $image->original_name ?: 'RX')"
                                            class="overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-sm hover:border-[#38B2AC]">
                                        <div class="aspect-[4/3] bg-slate-100">
                                            <img src="{{ route('client.telemedicine.radiology-images.show', [$share->token, $image]) }}" alt="{{ $image->label ?? $image->original_name ?? 'RX' }}" class="h-full w-full object-cover">
                                        </div>
                                        <div class="p-4">
                                            <p class="text-xs font-black text-[#0F172A]">{{ $image->label ?: 'RX' }}</p>
                                            <p class="mt-1 text-[11px] font-semibold text-slate-400">
                                                {{ \Illuminate\Support\Str::limit($image->original_name ?? 'Imagen', 34) }}
                                                @if($image->size)
                                                    &middot; {{ number_format($image->size / 1048576, 1) }} MB
                                                @endif
                                            </p>
                                        </div>
                                    </button>
                                @empty
                                    <div class="md:col-span-2 xl:col-span-3 rounded-2xl border border-dashed border-slate-200 px-6 py-12 text-center">
                                        <p class="text-sm font-black text-[#0F172A]">Carpeta sin RX</p>
                                        <p class="mt-2 text-xs font-semibold text-slate-400">No hay imagenes visibles en este estudio.</p>
                                    </div>
                                @endforelse
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
                <p class="text-xs font-semibold text-slate-400 mt-2">Este espacio queda visible en modo lectura para futuras secciones clinicas extendidas.</p>
            </div>
        </div>
    </div>
</div>
@endsection
