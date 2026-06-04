@extends('layouts.client')

@section('title', 'Expediente Compartido')

@section('content')
<div class="space-y-6" x-data="{ tab: 'datos' }">
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

        <div x-show="tab === 'extra'" class="p-6" x-cloak>
            <div class="border border-dashed border-slate-200 rounded-2xl px-6 py-12 text-center">
                <p class="text-sm font-black text-[#0F172A]">Mas informacion del paciente</p>
                <p class="text-xs font-semibold text-slate-400 mt-2">Este espacio queda visible en modo lectura para futuras secciones clinicas extendidas.</p>
            </div>
        </div>
    </div>
</div>
@endsection
