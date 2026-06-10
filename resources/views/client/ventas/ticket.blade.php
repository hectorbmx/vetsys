@extends(isset($isPublic) && $isPublic ? 'layouts.public' : 'layouts.client')

@section('content')
<div class="max-w-xl mx-auto px-4 py-8">
    {{-- Botones de acción --}}
    <div class="flex flex-wrap justify-between items-center gap-4 mb-6 print:hidden">
        @if(!isset($isPublic) || !$isPublic)
            <a href="{{ route('client.ventas.show', $note) }}" class="text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors">
                ← Volver a la nota
            </a>
            
            <div class="flex items-center gap-2">
                {{-- Boton WhatsApp --}}
                @php
                    $publicUrl = route('public.ventas.ticket', $note->public_token);
                    $whatsappMessage = "Hola " . $note->customer->name . ", adjunto el ticket de su visita en " . $tenant->name . ": " . $publicUrl;
                    $whatsappUrl = "https://wa.me/" . preg_replace('/[^0-9]/', '', $note->customer->phone) . "?text=" . urlencode($whatsappMessage);
                @endphp
                
                @if($note->customer->phone)
                    <a href="{{ $whatsappUrl }}" target="_blank" class="bg-[#25D366] text-white px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-[#128C7E] transition-colors flex items-center gap-2">
                        <span>WhatsApp</span>
                    </a>
                @endif

                <button onclick="window.print()" class="bg-[#0F172A] text-white px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-slate-800 transition-colors">
                    🖨️ Imprimir
                </button>
            </div>
        @else
            <div class="w-full flex justify-center">
                 <button onclick="window.print()" class="bg-[#0F172A] text-white px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-slate-800 transition-colors">
                    🖨️ Imprimir Ticket
                </button>
            </div>
        @endif
    </div>

    {{-- Ticket Card --}}
    <div class="bg-white border border-slate-200 shadow-xl rounded-[32px] overflow-hidden print:shadow-none print:border-none">
        {{-- Header con info del Tenant --}}
        <div class="bg-slate-50 p-8 text-center border-b border-dashed border-slate-200">
            <h2 class="text-xl font-black text-[#0F172A] uppercase tracking-tighter">{{ $tenant->name }}</h2>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mt-1">Ticket de Venta</p>
            <div class="mt-4 flex flex-col items-center gap-1">
                <span class="text-xs font-bold text-[#0F172A]">{{ $note->folio }}</span>
                <span class="text-[10px] font-medium text-slate-400">{{ $note->date_at->format('d/m/Y H:i') }}</span>
            </div>
        </div>

        {{-- Info del Cliente --}}
        <div class="px-8 py-6 border-b border-dashed border-slate-200">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Cliente</p>
            <p class="text-sm font-black text-[#0F172A]">{{ $note->customer->full_name }}</p>
            @if($note->customer->phone)
                <p class="text-xs font-medium text-slate-500 mt-0.5">{{ $note->customer->phone }}</p>
            @endif
        </div>

        {{-- Desglose por Mascota --}}
        <div class="px-8 py-6 space-y-6">
            @foreach($detailsByAnimal as $animalId => $details)
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                        <p class="text-[10px] font-black text-[#0F172A] uppercase tracking-wider">
                            Paciente: {{ $details->first()->animal->name ?? 'N/A' }}
                        </p>
                    </div>
                    
                    <div class="space-y-3">
                        @foreach($details as $detail)
                            <div class="flex justify-between items-start gap-4">
                                <div class="flex-1">
                                    <p class="text-xs font-bold text-[#0F172A]">{{ $detail->catalogItem->name }}</p>
                                    <p class="text-[10px] font-medium text-slate-400">
                                        {{ $detail->quantity }} {{ $detail->quantity > 1 ? 'unidades' : 'unidad' }} x ${{ number_format($detail->price_at_sale, 2) }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-black text-[#0F172A]">${{ number_format($detail->subtotal, 2) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Totales --}}
        <div class="bg-slate-50 px-8 py-6 border-t border-dashed border-slate-200 space-y-2">
            <div class="flex justify-between items-center">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Total</p>
                <p class="text-xl font-black text-[#0F172A]">${{ number_format($note->total, 2) }}</p>
            </div>
            
            <div class="flex justify-between items-center pt-2">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Estatus</p>
                @if($note->status === 'PAGADA')
                    <span class="text-[10px] font-black uppercase tracking-widest text-emerald-600">Completado</span>
                @else
                    <span class="text-[10px] font-black uppercase tracking-widest text-amber-600">Pendiente de Pago</span>
                @endif
            </div>
        </div>

        {{-- Footer --}}
        <div class="px-8 py-6 text-center">
            <p class="text-[10px] font-medium text-slate-400 italic">¡Gracias por su confianza!</p>
            <div class="mt-4 flex justify-center opacity-20 grayscale">
                {{-- Logo o Icono placeholder --}}
                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
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
