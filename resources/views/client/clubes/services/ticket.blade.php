@extends('layouts.client')

@section('content')
<div class="max-w-xl mx-auto px-4 py-8">
    <div class="flex flex-wrap justify-between items-center gap-4 mb-6 print:hidden">
        <a href="{{ route('client.clubes.edit', ['clube' => $club, 'tab' => 'servicios']) }}" class="text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors">
            ← Volver al club
        </a>

        <button onclick="window.print()" class="bg-[#0F172A] text-white px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-slate-800 transition-colors">
            Imprimir
        </button>
    </div>

    <div class="bg-white border border-slate-200 shadow-xl rounded-[32px] overflow-hidden print:shadow-none print:border-none">
        <div class="bg-slate-50 p-8 text-center border-b border-dashed border-slate-200">
            <h2 class="text-xl font-black text-[#0F172A] uppercase tracking-tighter">{{ $tenant->name }}</h2>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mt-1">Nota de club</p>
            <div class="mt-4 flex flex-col items-center gap-1">
                <span class="text-xs font-bold text-[#0F172A]">{{ $clubNote->folio }}</span>
                <span class="text-[10px] font-medium text-slate-400">{{ $clubNote->date_at?->format('d/m/Y') }}</span>
            </div>
        </div>

        <div class="px-8 py-6 border-b border-dashed border-slate-200">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Club</p>
            <p class="text-sm font-black text-[#0F172A]">{{ $club->name }}</p>
            @if($club->description)
                <p class="text-xs font-medium text-slate-500 mt-0.5">{{ $club->description }}</p>
            @endif
        </div>

        <div class="px-8 py-6 space-y-4">
            @foreach($clubNote->details as $detail)
                <div class="flex justify-between items-start gap-4">
                    <div class="flex-1">
                        <p class="text-xs font-bold text-[#0F172A]">{{ $detail->catalogItem?->name ?? 'Concepto eliminado' }}</p>
                        <p class="text-[10px] font-medium text-slate-400">
                            {{ number_format((float) $detail->quantity, 0) }} {{ (float) $detail->quantity > 1 ? 'unidades' : 'unidad' }} x ${{ number_format((float) $detail->price_at_sale, 2) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-black text-[#0F172A]">${{ number_format((float) $detail->subtotal, 2) }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="bg-slate-50 px-8 py-6 border-t border-dashed border-slate-200 space-y-2">
            <div class="flex justify-between items-center">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Total</p>
                <p class="text-xl font-black text-[#0F172A]">${{ number_format((float) $clubNote->total, 2) }}</p>
            </div>

            <div class="flex justify-between items-center pt-2">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Estatus</p>
                <span class="text-[10px] font-black uppercase tracking-widest text-amber-600">{{ $clubNote->status }}</span>
            </div>
        </div>

        <div class="px-8 py-6 text-center">
            <p class="text-[10px] font-medium text-slate-400 italic">Gracias por su confianza.</p>
        </div>
    </div>
</div>

<style>
    @media print {
        body { background: white !important; }
        .print\:hidden { display: none !important; }
        .shadow-xl { box-shadow: none !important; }
        .rounded-\[32px\] { border-radius: 0 !important; }
    }
</style>
@endsection
