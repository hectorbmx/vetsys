@extends('layouts.client')

@section('content')
<div class="p-6 max-w-7xl mx-auto space-y-6">

    {{-- ENCABEZADO --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-xl font-black text-[#0F172A] uppercase tracking-widest">Historial de Ventas</h1>
            <p class="text-xs text-slate-400 font-medium mt-0.5">Monitorea los folios emitidos, estados de cuenta de clientes y cuentas por cobrar.</p>
        </div>
        <a href="{{ route('client.ventas.create') }}" class="bg-[#0F172A] hover:bg-slate-800 text-white px-5 py-3 rounded-xl font-bold text-xs tracking-wide shadow-sm transition-all flex items-center gap-2">
            + Nueva Nota de Venta
        </a>
    </div>

    {{-- TABLA HISTÓRICA --}}
    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/50">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Folio</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Fecha</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Cliente / Propietario</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Monto Total</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Saldo Pendiente</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($notes as $note)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            {{-- Folio --}}
                            <td class="px-6 py-4 text-xs font-mono font-bold text-[#0F172A]">
                                {{ $note->folio }}
                            </td>

                            {{-- Fecha --}}
                            <td class="px-6 py-4 text-xs font-semibold text-slate-600">
                                {{ $note->date_at->format('d/m/Y') }}
                            </td>

                            {{-- Cliente --}}
                            {{-- <td class="px-6 py-4">
                                <span class="text-xs font-bold text-[#0F172A] block">{{ $note->customer->full_name }}</span>
                                <span class="text-[10px] text-slate-400 font-medium block">📞 {{ $note->customer->phone ?? 'Sin Teléfono' }}</span>
                            </td> --}}
                            {{-- Cliente --}}
                            <td class="px-6 py-4">
                                <a href="{{ route('client.customers.show', $note->customer->id) }}" 
                                class="text-xs font-bold text-[#0F172A] block hover:text-blue-600 hover:underline transition-colors">
                                    {{ $note->customer->full_name }}
                                </a>
                                <span class="text-[10px] text-slate-400 font-medium block">📞 {{ $note->customer->phone ?? 'Sin Teléfono' }}</span>
                            </td>

                            {{-- Total --}}
                            <td class="px-6 py-4 text-xs font-black text-[#0F172A]">
                                ${{ number_format($note->total, 2) }}
                            </td>

                            {{-- Saldo Pendiente --}}
                            
                            <td class="px-6 py-4 text-xs font-bold">
                                @if($note->balance > 0)
                                    <span class="text-rose-600">${{ number_format($note->balance, 2) }}</span>
                                @else
                                    <span class="text-slate-400 font-medium">$0.00</span>
                                @endif
                            </td>

                            {{-- Estado Badge --}}
                            <td class="px-6 py-4">
                                @if($note->status === 'PAGADA')
                                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">
                                        🟢 Pagada
                                    </span>
                                @elseif($note->status === 'PENDIENTE')
                                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full">
                                        🟡 Crédito / Pendiente
                                    </span>
                                @else
                                    <span class="inline-flex text-[9px] font-black uppercase tracking-widest text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">
                                        ⚪ Cancelada
                                    </span>
                                @endif
                            </td>

                            {{-- Acciones --}}
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('client.ventas.show', $note->id) }}"
                                    class="px-3 py-1.5 bg-slate-50 border border-slate-200 hover:border-slate-300 rounded-lg text-[11px] font-bold text-slate-700 transition-colors shadow-sm">
                                        Ver Detalle
                                    </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-sm font-bold text-slate-400">
                                No se han registrado notas de venta en este periodo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection