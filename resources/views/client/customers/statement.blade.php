<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Estado de Cuenta — {{ $customer->full_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #1e293b;
            background: #ffffff;
        }

        /* ── CABECERA ─────────────────────────────────────────── */
        .header {
            padding: 20px 24px 16px;
            border-bottom: 3px solid #38B2AC;
            margin-bottom: 16px;
        }
        .header-inner {
            width: 100%;
        }
        .header-left {
            display: inline-block;
            width: 60%;
            vertical-align: top;
        }
        .header-right {
            display: inline-block;
            width: 38%;
            vertical-align: top;
            text-align: right;
        }
        .vet-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .vet-meta {
            font-size: 8px;
            color: #64748b;
            line-height: 1.6;
        }
        .doc-title {
            font-size: 13px;
            font-weight: 700;
            color: #38B2AC;
            margin-bottom: 4px;
        }
        .doc-meta {
            font-size: 8px;
            color: #64748b;
            line-height: 1.6;
        }

        /* ── DATOS DEL CLIENTE ──────────────────────────────────── */
        .client-box {
            margin: 0 24px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
        }
        .client-box-title {
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin-bottom: 5px;
        }
        .client-name {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }
        .client-meta {
            font-size: 8px;
            color: #64748b;
            margin-top: 2px;
        }

        /* ── SECCIÓN MES ────────────────────────────────────────── */
        .month-section {
            margin: 0 24px 14px;
        }
        .month-title {
            font-size: 10px;
            font-weight: 700;
            color: #0f172a;
            background: #f1f5f9;
            border-left: 3px solid #38B2AC;
            padding: 5px 10px;
            margin-bottom: 6px;
        }

        /* ── NOTA ───────────────────────────────────────────────── */
        .note-block {
            margin-bottom: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .note-header {
            background: #ffffff;
            padding: 6px 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .note-folio {
            font-size: 9px;
            font-weight: 700;
            color: #0f172a;
            display: inline-block;
        }
        .note-date {
            font-size: 8px;
            color: #94a3b8;
            display: inline-block;
            margin-left: 8px;
        }
        .note-total-badge {
            float: right;
            font-size: 8px;
            font-weight: 700;
            color: #0f172a;
        }

        /* ── MASCOTA ────────────────────────────────────────────── */
        .animal-block {
            padding: 4px 10px 4px 18px;
            border-bottom: 1px solid #f8fafc;
        }
        .animal-name {
            font-size: 8px;
            font-weight: 700;
            color: #475569;
            padding: 3px 0 2px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        /* ── TABLA DE SERVICIOS ─────────────────────────────────── */
        .services-table {
            width: 100%;
            border-collapse: collapse;
        }
        .services-table th {
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94a3b8;
            padding: 3px 6px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        .services-table th.right { text-align: right; }
        .services-table td {
            font-size: 8px;
            color: #334155;
            padding: 4px 6px;
            border-bottom: 1px solid #f8fafc;
            vertical-align: top;
        }
        .services-table td.right { text-align: right; }
        .services-table tr:last-child td { border-bottom: none; }

        /* ── NOTA SIN MASCOTA ───────────────────────────────────── */
        .no-animal-block {
            padding: 4px 10px 4px 18px;
        }

        /* ── TOTALES DE NOTA ────────────────────────────────────── */
        .note-footer {
            background: #f8fafc;
            padding: 5px 10px;
            text-align: right;
            border-top: 1px solid #e2e8f0;
        }
        .note-footer-label {
            font-size: 7px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: inline-block;
            margin-right: 8px;
        }
        .note-footer-value {
            font-size: 9px;
            font-weight: 700;
            color: #0f172a;
            display: inline-block;
            min-width: 70px;
            text-align: right;
        }
        .note-footer-balance {
            color: #e11d48;
        }
        .note-footer-paid {
            color: #10b981;
        }

        /* ── SECCIÓN PAGOS ──────────────────────────────────────── */
        .payments-section {
            margin: 0 24px 14px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 10px;
            font-weight: 700;
            color: #0f172a;
            background: #f1f5f9;
            border-left: 3px solid #10b981;
            padding: 5px 10px;
            margin-bottom: 6px;
        }
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .payments-table th {
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94a3b8;
            padding: 6px 10px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .payments-table th.right { text-align: right; }
        .payments-table td {
            font-size: 8px;
            color: #334155;
            padding: 6px 10px;
            border-bottom: 1px solid #f8fafc;
        }
        .payments-table td.right { text-align: right; font-weight: 700; color: #10b981; }
        .payments-table tr:last-child td { border-bottom: none; }

        /* ── RESUMEN FINAL ──────────────────────────────────────── */
        .summary-section {
            margin: 0 24px 20px;
            page-break-inside: avoid;
        }
        .summary-box {
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }
        .summary-row {
            padding: 7px 14px;
            border-bottom: 1px solid #f1f5f9;
            overflow: hidden;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-label {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            display: inline-block;
            width: 60%;
        }
        .summary-value {
            font-size: 10px;
            font-weight: 700;
            color: #0f172a;
            display: inline-block;
            width: 39%;
            text-align: right;
        }
        .summary-value.paid   { color: #10b981; }
        .summary-value.debt   { color: #e11d48; }
        .summary-value.total  { color: #0f172a; font-size: 12px; }
        .summary-highlight {
            background: #f8fafc;
        }

        /* ── FOOTER ─────────────────────────────────────────────── */
        .footer {
            margin: 0 24px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 7px;
            color: #cbd5e1;
        }
    </style>
</head>
<body>

    {{-- ══════════════════════════════════════════════════════════
         CABECERA
    ══════════════════════════════════════════════════════════ --}}
    <div class="header">
        <div class="header-inner">
            <div class="header-left">
                <div class="vet-name">{{ $tenant->business_name ?? $tenant->name }}</div>
                <div class="vet-meta">
                    @if($tenant->email) {{ $tenant->email }}<br>@endif
                    @if($tenant->phone) Tel. {{ $tenant->phone }}@endif
                </div>
            </div>
            <div class="header-right">
                <div class="doc-title">Estado de Cuenta</div>
                <div class="doc-meta">
                    Período: {{ $from->format('d/m/Y') }} — {{ $to->format('d/m/Y') }}<br>
                    Generado: {{ now()->format('d/m/Y H:i') }}
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         DATOS DEL CLIENTE
    ══════════════════════════════════════════════════════════ --}}
    <div class="client-box">
        <div class="client-box-title">Cliente</div>
        <div class="client-name">{{ $customer->full_name }}</div>
        <div class="client-meta">
            @if($customer->email) {{ $customer->email }} &nbsp;·&nbsp; @endif
            @if($customer->phone) Tel. {{ $customer->phone }} @endif
            @if($customer->address) <br>{{ $customer->address }} @endif
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         NOTAS POR MES
    ══════════════════════════════════════════════════════════ --}}
    @forelse($notesByMonth as $month => $notes)
        <div class="month-section">
            <div class="month-title">{{ $month }}</div>

            @foreach($notes as $note)
                @php
                    // Separar detalles con mascota y sin mascota
                    $withAnimal    = $note->details->whereNotNull('animal_id')->groupBy('animal_id');
                    $withoutAnimal = $note->details->whereNull('animal_id');
                @endphp

                <div class="note-block">

                    {{-- Cabecera nota --}}
                    <div class="note-header">
                        <span class="note-folio">{{ $note->folio }}</span>
                        <span class="note-date">{{ $note->date_at->format('d/m/Y') }}</span>
                        <span class="note-total-badge">Total: ${{ number_format($note->total, 2) }}</span>
                    </div>

                    {{-- Detalles agrupados por mascota --}}
                    @foreach($withAnimal as $animalId => $details)
                        @php $animal = $details->first()->animal; @endphp
                        <div class="animal-block">
                            <div class="animal-name">🐾 {{ $animal->name ?? 'Mascota #'.$animalId }}</div>
                            <table class="services-table">
                                <thead>
                                    <tr>
                                        <th style="width:50%">Servicio / Producto</th>
                                        <th class="right" style="width:15%">Cant.</th>
                                        <th class="right" style="width:17%">Precio</th>
                                        <th class="right" style="width:18%">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($details as $detail)
                                        <tr>
                                            <td>{{ $detail->catalogItem->name ?? 'Servicio eliminado' }}</td>
                                            <td class="right">{{ $detail->quantity }}</td>
                                            <td class="right">${{ number_format($detail->price_at_sale, 2) }}</td>
                                            <td class="right">${{ number_format($detail->subtotal, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach

                    {{-- Detalles sin mascota --}}
                    @if($withoutAnimal->isNotEmpty())
                        <div class="no-animal-block">
                            <div class="animal-name" style="color:#94a3b8;">General</div>
                            <table class="services-table">
                                <thead>
                                    <tr>
                                        <th style="width:50%">Servicio / Producto</th>
                                        <th class="right" style="width:15%">Cant.</th>
                                        <th class="right" style="width:17%">Precio</th>
                                        <th class="right" style="width:18%">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($withoutAnimal as $detail)
                                        <tr>
                                            <td>{{ $detail->catalogItem->name ?? 'Servicio eliminado' }}</td>
                                            <td class="right">{{ $detail->quantity }}</td>
                                            <td class="right">${{ number_format($detail->price_at_sale, 2) }}</td>
                                            <td class="right">${{ number_format($detail->subtotal, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- Footer de la nota: total + saldo --}}
                    <div class="note-footer">
                        <span class="note-footer-label">Pagado</span>
                        <span class="note-footer-value note-footer-paid">${{ number_format($note->amount_paid, 2) }}</span>
                        &nbsp;&nbsp;
                        <span class="note-footer-label">Saldo</span>
                        <span class="note-footer-value {{ $note->balance > 0 ? 'note-footer-balance' : 'note-footer-paid' }}">
                            ${{ number_format($note->balance, 2) }}
                        </span>
                    </div>

                </div>
            @endforeach
        </div>
    @empty
        <div style="margin: 0 24px; padding: 20px; text-align:center; color:#94a3b8; font-size:9px; border:1px dashed #e2e8f0; border-radius:6px;">
            No hay notas de venta en este período.
        </div>
    @endforelse

    {{-- ══════════════════════════════════════════════════════════
         PAGOS DEL PERÍODO
    ══════════════════════════════════════════════════════════ --}}
    <div class="payments-section">
        <div class="section-title">Pagos Registrados</div>

        @if($payments->isNotEmpty())
            <table class="payments-table">
                <thead>
                    <tr>
                        <th style="width:25%">Fecha</th>
                        <th style="width:45%">Referencia</th>
                        <th style="width:30%">Método</th>
                        <th class="right" style="width:20%">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $payment)
                        <tr>
                            <td>{{ $payment->created_at->format('d/m/Y') }}</td>
                            <td>{{ $payment->reference ?? 'Pago aplicado' }}</td>
                            <td>{{ $payment->paymentMethod->name ?? 'N/A' }}</td>
                            <td class="right">+${{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="padding: 12px; text-align:center; color:#94a3b8; font-size:9px; border:1px dashed #e2e8f0; border-radius:4px;">
                Sin pagos en este período.
            </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════════════
         RESUMEN FINAL
    ══════════════════════════════════════════════════════════ --}}
    <div class="summary-section">
        <div class="summary-box">
            @isset($previousBalance)
                <div class="summary-row">
                    <span class="summary-label">Saldo anterior</span>
                    <span class="summary-value {{ $previousBalance > 0 ? 'debt' : 'paid' }}">${{ number_format($previousBalance, 2) }}</span>
                </div>
            @endisset
            <div class="summary-row">
                <span class="summary-label">Total Facturado (período)</span>
                <span class="summary-value">${{ number_format($totalInvoiced, 2) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Total Pagado (período)</span>
                <span class="summary-value paid">${{ number_format($totalPaid, 2) }}</span>
            </div>
            <div class="summary-row summary-highlight">
                <span class="summary-label" style="color:#0f172a; font-size:9px;">Saldo Pendiente</span>
                <span class="summary-value {{ $totalDebt > 0 ? 'debt' : 'paid' }}">${{ number_format($totalDebt, 2) }}</span>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         FOOTER
    ══════════════════════════════════════════════════════════ --}}
    <div class="footer">
        Documento generado automáticamente · {{ $tenant->business_name ?? $tenant->name }} · {{ now()->format('d/m/Y H:i') }}
    </div>

</body>
</html>
