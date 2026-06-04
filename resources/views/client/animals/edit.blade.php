@extends('layouts.client')

@section('title', 'Editar Mascota')

@section('content')
<div class="space-y-6" x-data="{ tab: 'datos', loading: false }">
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
                <button type="button" @click="tab = 'extra'" :class="tab === 'extra' ? 'border-[#38B2AC] text-[#38B2AC]' : 'border-transparent text-slate-400 hover:text-slate-600'" class="px-4 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                    Mas Informacion
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

        <div x-show="tab === 'extra'" class="p-6" x-cloak>
            <div class="border border-dashed border-slate-200 rounded-2xl px-6 py-12 text-center">
                <p class="text-sm font-black text-[#0F172A]">Mas informacion del paciente</p>
                <p class="text-xs font-semibold text-slate-400 mt-2">Este espacio queda listo para vacunas, archivos, signos vitales o campos clinicos extendidos.</p>
            </div>
        </div>
    </div>
</div>
@endsection
