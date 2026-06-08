@extends('layouts.client')

@section('content')
<div class="p-6 max-w-7xl mx-auto space-y-6">

    {{-- ENCABEZADO --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-xl font-black text-[#0F172A] uppercase tracking-widest">Facturación</h1>
            <p class="text-xs text-slate-400 font-medium mt-0.5">
                Administra las notas pendientes por facturar, CFDI emitidos y configuración fiscal.
            </p>
        </div>

        <a href="{{ route('client.facturacion.notas') }}"
           class="bg-[#0F172A] hover:bg-slate-800 text-white px-5 py-3 rounded-xl font-bold text-xs tracking-wide shadow-sm transition-all flex items-center gap-2">
            + Facturar Nota
        </a>
    </div>

    {{-- CARDS RESUMEN --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

        <div class="bg-white border border-slate-200 rounded-[22px] p-5 shadow-sm">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Notas Pendientes</p>
            <h2 class="text-2xl font-black text-[#0F172A] mt-2">
                {{ $stats['notasPendientes'] ?? 0 }}
            </h2>
            <p class="text-[11px] text-slate-400 mt-1">Notas pagadas disponibles para CFDI</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-[22px] p-5 shadow-sm">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Facturas Emitidas</p>
            <h2 class="text-2xl font-black text-[#0F172A] mt-2">
                {{ $stats['facturasEmitidas'] ?? 0 }}
            </h2>
            <p class="text-[11px] text-slate-400 mt-1">CFDI generados en el periodo</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-[22px] p-5 shadow-sm">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Canceladas</p>
            <h2 class="text-2xl font-black text-[#0F172A] mt-2">
                {{ $stats['facturasCanceladas'] ?? 0 }}
            </h2>
            <p class="text-[11px] text-slate-400 mt-1">CFDI cancelados</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-[22px] p-5 shadow-sm">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Servicio</p>

            @if($stats['facturacionActiva'] ?? false)
                <h2 class="text-sm font-black text-emerald-700 mt-3 uppercase tracking-widest">
                    🟢 Activo
                </h2>
                <p class="text-[11px] text-slate-400 mt-1">Facturación habilitada</p>
            @else
                <h2 class="text-sm font-black text-amber-700 mt-3 uppercase tracking-widest">
                    🟡 Pendiente
                </h2>
                <p class="text-[11px] text-slate-400 mt-1">Configuración fiscal pendiente</p>
            @endif
        </div>

    </div>

    {{-- ACCESOS RÁPIDOS --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        <a href="{{ route('client.facturacion.notas') }}"
           class="bg-white border border-slate-200 rounded-[24px] p-6 shadow-sm hover:border-slate-300 hover:shadow-md transition-all block">
            <div class="text-2xl mb-3">🧾</div>
            <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Notas por Facturar</h3>
            <p class="text-xs text-slate-400 mt-2">
                Selecciona una nota pagada y genera el CFDI correspondiente.
            </p>
        </a>

        <a href="#"
           class="bg-white border border-slate-200 rounded-[24px] p-6 shadow-sm hover:border-slate-300 hover:shadow-md transition-all block">
            <div class="text-2xl mb-3">📄</div>
            <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Facturas Emitidas</h3>
            <p class="text-xs text-slate-400 mt-2">
                Consulta UUID, PDF, XML y estado fiscal de las facturas emitidas.
            </p>
        </a>

        <a href="#"
           class="bg-white border border-slate-200 rounded-[24px] p-6 shadow-sm hover:border-slate-300 hover:shadow-md transition-all block">
            <div class="text-2xl mb-3">⚙️</div>
            <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Configuración Fiscal</h3>
            <p class="text-xs text-slate-400 mt-2">
                Configura RFC, razón social, régimen fiscal, CSD y datos del emisor.
            </p>
        </a>

    </div>

    {{-- PANEL INFORMATIVO --}}
    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50">
            <h2 class="text-sm font-black text-[#0F172A] uppercase tracking-widest">Flujo de Facturación</h2>
            <p class="text-xs text-slate-400 mt-1">
                El CFDI se genera a partir de una nota de venta previamente pagada.
            </p>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="border border-slate-100 rounded-2xl p-4">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Paso 1</p>
                <h3 class="text-sm font-bold text-[#0F172A] mt-2">Crear Nota</h3>
                <p class="text-xs text-slate-400 mt-1">Se registra la venta de forma normal.</p>
            </div>

            <div class="border border-slate-100 rounded-2xl p-4">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Paso 2</p>
                <h3 class="text-sm font-bold text-[#0F172A] mt-2">Cobrar Nota</h3>
                <p class="text-xs text-slate-400 mt-1">La nota debe quedar en estado pagada.</p>
            </div>

            <div class="border border-slate-100 rounded-2xl p-4">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Paso 3</p>
                <h3 class="text-sm font-bold text-[#0F172A] mt-2">Seleccionar Nota</h3>
                <p class="text-xs text-slate-400 mt-1">El cliente solicita factura y eliges la nota.</p>
            </div>

            <div class="border border-slate-100 rounded-2xl p-4">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Paso 4</p>
                <h3 class="text-sm font-bold text-[#0F172A] mt-2">Emitir CFDI</h3>
                <p class="text-xs text-slate-400 mt-1">Se genera PDF, XML y UUID.</p>
            </div>
        </div>
    </div>

</div>
@endsection