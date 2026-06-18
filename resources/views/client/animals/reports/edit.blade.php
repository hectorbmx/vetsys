@extends('layouts.client')

@section('title', 'Editar reporte clinico')

@section('content')
<div x-data="{ reportSaving: false }" class="space-y-6">
    <div x-show="reportSaving" x-cloak class="fixed inset-0 z-[130] flex items-center justify-center theme-overlay px-4 backdrop-blur-sm">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
            <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 theme-border-primary-soft theme-spinner-primary"></div>
            <p class="mt-4 text-sm font-black uppercase tracking-widest theme-text-heading">Procesando reporte</p>
            <p class="mt-2 text-xs font-semibold text-slate-500">Guardando contenido, imagenes y PDF.</p>
        </div>
    </div>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Paciente: {{ $animal->name }}</p>
            <h1 class="mt-1 text-2xl font-black theme-text-heading">Editar borrador</h1>
        </div>
        <a href="{{ route('client.animals.edit', $animal) }}" class="rounded-xl bg-slate-100 px-4 py-2.5 text-center text-xs font-bold text-slate-600 hover:bg-slate-200">Volver al expediente</a>
    </div>

    @if($report->images->isNotEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="mb-4 text-xs font-black uppercase tracking-widest theme-text-heading">Imagenes actuales</p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach($report->images as $image)
                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <img src="{{ route('client.animal-report-images.show', $image) }}" alt="{{ $image->original_name }}" class="aspect-square w-full object-cover">
                        <div class="p-2">
                            <p class="truncate text-[10px] font-semibold text-slate-500">{{ $image->original_name }}</p>
                            <form action="{{ route('client.animal-report-images.destroy', $image) }}" method="POST" class="mt-2" onsubmit="return confirm('Eliminar esta imagen?')">
                                @csrf @method('DELETE')
                                <button class="text-[9px] font-black uppercase tracking-widest text-rose-600">Eliminar</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @include('client.animals.reports.form', ['report' => $report, 'animal' => $animal])
    </div>
</div>
@endsection
