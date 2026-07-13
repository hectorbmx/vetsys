@extends('layouts.client')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
    <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Estado de cuenta</p>
                <h1 class="mt-1 text-2xl font-black theme-text-heading">{{ $customer->full_name }}</h1>
                <p class="mt-1 text-xs font-bold uppercase tracking-widest text-slate-400">
                    {{ $from->format('d/m/Y') }} - {{ $to->format('d/m/Y') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('client.customers.show', ['customer' => $customer->id, 'tab' => 'notas']) }}"
                   class="rounded-xl bg-slate-100 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-slate-600 transition hover:bg-slate-200">
                    Volver a cuentas
                </a>
                <a href="{{ route('client.customers.statements.pdf', [$customer, $statement]) }}"
                   target="_blank"
                   class="theme-button-primary rounded-xl px-4 py-2.5 text-[11px] font-black uppercase tracking-widest shadow-sm transition-all">
                    Abrir PDF
                </a>
                <form action="{{ route('client.customers.statements.recalculate', [$customer, $statement]) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-amber-700 transition hover:bg-amber-100">
                        Recalcular
                    </button>
                </form>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 px-5 py-3 text-sm font-bold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-rose-100 bg-rose-50 px-5 py-3 text-sm font-bold text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-[20px] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Saldo anterior</p>
            <p class="mt-3 text-2xl font-black theme-text-heading">${{ number_format((float) $previousBalance, 2) }}</p>
        </div>
        <div class="rounded-[20px] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Cargos del periodo</p>
            <p class="mt-3 text-2xl font-black theme-text-heading">${{ number_format((float) $totalInvoiced, 2) }}</p>
        </div>
        <div class="rounded-[20px] border border-emerald-100 bg-emerald-50 p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-emerald-700">Abonos del periodo</p>
            <p class="mt-3 text-2xl font-black text-emerald-700">${{ number_format((float) $totalPaid, 2) }}</p>
        </div>
        <div class="rounded-[20px] border border-rose-100 bg-rose-50 p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-rose-700">Balance final</p>
            <p class="mt-3 text-2xl font-black text-rose-700">${{ number_format((float) $totalDebt, 2) }}</p>
        </div>
    </div>

    <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex flex-col gap-1">
            <h2 class="text-sm font-black uppercase tracking-widest theme-text-heading">Cargos del periodo</h2>
            <p class="text-[11px] font-semibold text-slate-400">Servicios aplicados al cliente dentro del rango del corte.</p>
        </div>

        @if($usesMonthlyCutoffBilling)
            @forelse($serviceDetailsByMonth ?? collect() as $month => $details)
                <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200">
                    <div class="border-l-4 theme-border-primary bg-slate-50 px-5 py-3 text-sm font-black theme-text-heading">
                        {{ $month }}
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left">
                            <thead>
                                <tr class="border-b border-slate-100 bg-white text-[10px] uppercase tracking-widest text-slate-400">
                                    <th class="px-5 py-3">Paciente</th>
                                    <th class="px-5 py-3">Servicio</th>
                                    <th class="px-5 py-3 text-right">Cant.</th>
                                    <th class="px-5 py-3 text-right">Precio</th>
                                    <th class="px-5 py-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @php
                                    $groupedDetails = $details->groupBy(function ($detail) {
                                        $animalId = $detail->animal_id ?? $detail->animal?->id ?? 'no-animal';
                                        $noteId = $detail->note_id ?? $detail->note?->id ?? 'no-note';

                                        return $animalId . '|' . $noteId;
                                    });
                                @endphp

                                @foreach($groupedDetails as $group)
                                    @foreach($group->values() as $index => $detail)
                                        <tr>
                                            @if($index === 0)
                                                <td rowspan="{{ $group->count() }}" class="px-5 py-3 align-top">
                                                    <p class="text-xs font-black theme-text-heading">{{ $detail->animal->name ?? 'Sin paciente' }}</p>
                                                    <p class="mt-1 text-[10px] font-semibold text-slate-400">
                                                        {{ $detail->note?->date_at?->format('d/m/Y') ?? '--' }}
                                                        @if($detail->note?->folio)
                                                            - Ref. {{ $detail->note->folio }}
                                                        @endif
                                                    </p>
                                                </td>
                                            @endif
                                            <td class="px-5 py-3 text-xs font-bold text-slate-600">{{ $detail->catalogItem->name ?? 'Servicio eliminado' }}</td>
                                            <td class="px-5 py-3 text-right text-xs font-bold text-slate-600">{{ number_format((float) $detail->quantity, 2) }}</td>
                                            <td class="px-5 py-3 text-right text-xs font-bold text-slate-600">${{ number_format((float) $detail->price_at_sale, 2) }}</td>
                                            <td class="px-5 py-3 text-right text-xs font-black theme-text-heading">${{ number_format((float) $detail->subtotal, 2) }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-xs font-bold text-slate-400">
                    No hay servicios registrados en este periodo.
                </div>
            @endforelse
        @else
            @forelse($notesByMonth as $month => $notes)
                <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200">
                    <div class="border-l-4 theme-border-primary bg-slate-50 px-5 py-3 text-sm font-black theme-text-heading">
                        {{ $month }}
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach($notes as $note)
                            <a href="{{ route('client.ventas.show', $note) }}" class="block px-5 py-4 transition hover:bg-slate-50">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-black theme-text-heading">{{ $note->folio }}</p>
                                        <p class="mt-1 text-[10px] font-semibold text-slate-400">{{ $note->date_at?->format('d/m/Y') }}</p>
                                    </div>
                                    <p class="text-sm font-black theme-text-heading">${{ number_format((float) $note->total, 2) }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-xs font-bold text-slate-400">
                    No hay notas registradas en este periodo.
                </div>
            @endforelse
        @endif
    </div>

    <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex flex-col gap-1">
            <h2 class="text-sm font-black uppercase tracking-widest theme-text-heading">Abonos registrados</h2>
            <p class="text-[11px] font-semibold text-slate-400">Pagos aplicados al cliente dentro del rango del corte.</p>
        </div>

        @if($payments->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] uppercase tracking-widest text-slate-400">
                            <th class="pb-3">Fecha</th>
                            <th class="pb-3">Referencia</th>
                            <th class="pb-3">Metodo</th>
                            <th class="pb-3 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($payments as $payment)
                            <tr>
                                <td class="py-4 text-xs font-bold theme-text-heading">{{ $payment->created_at?->format('d/m/Y') }}</td>
                                <td class="py-4 text-xs font-semibold text-slate-500">{{ $payment->reference ?: 'Pago aplicado' }}</td>
                                <td class="py-4 text-xs font-semibold text-slate-500">{{ $payment->paymentMethod->name ?? '-' }}</td>
                                <td class="py-4 text-right text-xs font-black text-emerald-600">+${{ number_format((float) $payment->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-xs font-bold text-slate-400">
                No hay abonos registrados en este periodo.
            </div>
        @endif
    </div>
</div>
@endsection
