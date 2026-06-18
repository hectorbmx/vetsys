@extends('layouts.client')

@section('title', 'Clientes')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{ loading: false }">

    {{-- Encabezado / Breadcrumbs --}}
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-black text-slate-400 uppercase tracking-widest mb-2">
                <a href="{{ route('client.mi-configuracion.index') }}" class="theme-hover-text-primary transition-colors">Configuración</a>
                <span>/</span>
                <span class="text-slate-600">Campos Personalizados</span>
            </div>
            <h1 class="text-3xl font-black theme-text-heading tracking-tighter">
                Campos Clínicos para: <span class="theme-text-primary">{{ $animalType->name }}</span>
            </h1>
            <p class="text-sm text-slate-500 font-medium mt-1">
                Define qué información extra e historial clínico deseas capturar de esta especie.
            </p>
        </div>

        {{-- Botón Regresar --}}
        <div>
            <a href="{{ route('client.mi-configuracion.index') }}" class="inline-flex items-center gap-2 px-5 py-3 bg-white border border-slate-200 hover:border-slate-300 rounded-xl text-xs font-black theme-text-heading uppercase tracking-widest transition-all shadow-sm">
                ← Volver al Panel
            </a>
        </div>
    </div>

    {{-- Grid Principal: Formulario a la izquierda, Listado a la derecha --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        {{-- COLUMNA IZQUIERDA: Formulario de Registro --}}
        <div class="lg:col-span-1 bg-white rounded-[24px] border border-slate-100 shadow-xl overflow-hidden relative">
            
            {{-- Spinner de carga interno --}}
            <div x-show="loading" class="absolute inset-0 bg-white/80 backdrop-blur-md z-50 flex flex-col items-center justify-center gap-4" style="display: none;">
                <div class="w-8 h-8 border-4 border-slate-200 theme-spinner-primary rounded-full animate-spin"></div>
                <p class="text-[10px] font-black theme-text-heading uppercase tracking-widest animate-pulse">Creando Campo...</p>
            </div>

            <div class="px-6 py-5 bg-slate-50/50 border-b border-slate-100">
                <h3 class="font-black theme-text-heading text-sm uppercase tracking-wider">Nuevo Campo Extra</h3>
            </div>

            
                <form action="{{ route('client.mi-configuracion.fields.store', $animalType->id) }}" method="POST" @submit="loading = true" class="p-6 space-y-4">
                
                @csrf

                {{-- Etiqueta del Campo --}}
                <div class="space-y-2">
                    <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Nombre / Etiqueta *</label>
                    <input type="text" name="label" required placeholder="Ej. Número de Registro, Pedigree" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-inner">
                </div>

                {{-- Tipo de Entrada --}}
                <div class="space-y-2">
                    <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Tipo de Dato *</label>
                    <select name="field_type" required class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-inner cursor-pointer">
                        <option value="text">Texto Corto (Línea única)</option>
                        <option value="textarea">Texto Largo (Párrafo / Notas)</option>
                        <option value="number">Número Entero</option>
                        <option value="decimal">Número Decimal (Ej. Dosis, Medidas)</option>
                        <option value="date">Fecha (Calendario)</option>
                        <option value="datetime">Fecha y Hora</option>
                        <option value="boolean">Interruptor (Sí / No)</option>
                    </select>
                </div>

                {{-- Texto de Ayuda (Help Text) --}}
                <div class="space-y-2">
                    <label class="block text-[10px] font-black theme-text-heading uppercase tracking-widest">Texto de ayuda (Opcional)</label>
                    <input type="text" name="help_text" placeholder="Ej. Visible debajo del campo como guía" class="w-full bg-slate-50/80 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold theme-text-heading focus:bg-white theme-input focus:ring-4 theme-ring-primary transition-all outline-none shadow-inner">
                </div>

                {{-- Obligatorio (is_required) --}}
                <div class="pt-2">
                    <label class="relative flex items-center cursor-pointer select-none">
                        <input type="checkbox" name="is_required" value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all theme-peer-checked-bg-primary"></div>
                        <span class="ms-3 text-xs font-black theme-text-heading uppercase tracking-wider">¿Es campo obligatorio?</span>
                    </label>
                </div>

                {{-- Botón Guardar --}}
                <div class="pt-4 border-t border-slate-100">
                    <button type="submit" class="w-full theme-button-dark py-3.5 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-800 shadow-lg transition-colors">
                        Agregar Campo Extra
                    </button>
                </div>
            </form>
        </div>

        {{-- COLUMNA DERECHA: Listado de Campos Existentes --}}
        <div class="lg:col-span-2 bg-white rounded-[24px] border border-slate-100 shadow-xl overflow-hidden">
            <div class="px-6 py-5 bg-slate-50/50 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-black theme-text-heading text-sm uppercase tracking-wider">Campos Habilitados ({{ $fields->count() }})</h3>
                <span class="px-2.5 py-1 bg-slate-100 theme-text-heading text-[10px] font-black uppercase tracking-widest rounded-md">N Cantidad</span>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($fields as $field)
                    <div class="p-6 flex items-center justify-between hover:bg-slate-50/50 transition-colors">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <h4 class="font-black theme-text-heading text-base tracking-tight">{{ $field->label }}</h4>
                                @if($field->is_required)
                                    <span class="bg-red-50 text-red-600 text-[9px] font-black px-2 py-0.5 rounded-md uppercase tracking-widest border border-red-100">
                                        Obligatorio
                                    </span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 font-medium">
                                <span>Tipo: <strong class="text-slate-700 uppercase text-[10px] font-extrabold bg-slate-100 px-1.5 py-0.5 rounded">{{ $field->field_type }}</strong></span>
                                <span>Slug: <code class="text-slate-400 bg-slate-50 px-1 rounded text-[11px]">{{ $field->slug }}</code></span>
                                @if($field->help_text)
                                    <span class="text-slate-400 italic">💡 {{ $field->help_text }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Acciones del Campo (Futuro Editar/Eliminar) --}}
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-300 bg-slate-50 px-2 py-1 rounded-md border border-slate-100">
                                Orden #{{ $field->sort_order }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-3 border border-slate-100">
                            <span class="text-slate-400 text-lg">📝</span>
                        </div>
                        <h4 class="text-sm font-black theme-text-heading uppercase tracking-wider">Sin campos personalizados</h4>
                        <p class="text-xs text-slate-400 font-medium mt-1 max-w-sm mx-auto">
                            Esta especie solo capturará los campos básicos del sistema por ahora (Nombre, Fecha de Nacimiento, Sexo, Peso y Notas).
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection