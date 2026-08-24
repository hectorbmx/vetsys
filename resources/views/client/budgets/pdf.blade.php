<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Presupuesto {{ $budget->folio }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #1e293b;
            background: #ffffff;
        }
        .header {
            padding: 22px 26px 16px;
            background: {{ $documentTemplate['header_color'] ?? '#38B2AC' }};
            margin-bottom: 16px;
        }
        .header-left {
            display: inline-block;
            width: 59%;
            vertical-align: top;
        }
        .header-right {
            display: inline-block;
            width: 39%;
            text-align: right;
            vertical-align: top;
        }
        .tenant-logo {
            display: inline-block;
            width: 44px;
            max-height: 44px;
            margin-right: 10px;
            vertical-align: top;
        }
        .tenant-logo img {
            width: 44px;
            max-height: 44px;
            object-fit: contain;
        }
        .tenant-meta {
            display: inline-block;
            max-width: 270px;
            vertical-align: top;
        }
        .tenant-name {
            margin-bottom: 3px;
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
        }
        .muted {
            color: #64748b;
            line-height: 1.55;
        }
        .header .muted {
            color: #e2e8f0;
        }
        .doc-title {
            margin-bottom: 4px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
        }
        .doc-meta {
            color: #e2e8f0;
            font-size: 8px;
            line-height: 1.65;
        }
        .summary-box {
            margin: 0 26px 16px;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            background: #f8fafc;
        }
        .summary-left {
            display: inline-block;
            width: 62%;
            vertical-align: middle;
        }
        .summary-right {
            display: inline-block;
            width: 36%;
            text-align: right;
            vertical-align: middle;
        }
        .label {
            margin-bottom: 5px;
            color: #94a3b8;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .client-name {
            color: #0f172a;
            font-size: 12px;
            font-weight: 700;
        }
        .total-value {
            color: #0f172a;
            font-size: 19px;
            font-weight: 700;
        }
        .animal-section {
            margin: 0 26px 12px;
            page-break-inside: avoid;
        }
        .animal-header {
            padding: 7px 10px;
            border-left: 3px solid {{ $documentTemplate['header_color'] ?? '#38B2AC' }};
            background: #f1f5f9;
        }
        .animal-name {
            display: inline-block;
            width: 68%;
            color: #0f172a;
            font-size: 10px;
            font-weight: 700;
        }
        .animal-subtotal {
            display: inline-block;
            width: 30%;
            text-align: right;
            color: #0f172a;
            font-size: 10px;
            font-weight: 700;
        }
        .animal-notes {
            padding: 7px 10px;
            border-right: 1px solid #e2e8f0;
            border-left: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e2e8f0;
        }
        th {
            padding: 5px 7px;
            border-bottom: 1px solid #e2e8f0;
            background: {{ $documentTemplate['header_color'] ?? '#0f172a' }};
            color: #ffffff;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: .06em;
            text-align: left;
            text-transform: uppercase;
        }
        th.right, td.right { text-align: right; }
        td {
            padding: 6px 7px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 8px;
            vertical-align: top;
        }
        tr:nth-child(even) td { background: #f8fafc; }
        tr:last-child td { border-bottom: none; }
        .service-name {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }
        .notes-box {
            position: relative;
            margin: 0 26px 12px;
            min-height: 118px;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #f8fafc;
        }
        .notes-content {
            color: #64748b;
            font-size: 8px;
            line-height: 1.55;
        }
        .notes-content p { margin: 0 0 5px; }
        .notes-content ul,
        .notes-content ol { margin: 4px 0 6px 16px; }
        .notes-content strong,
        .notes-content b { font-weight: 700; }
        .notes-content blockquote {
            margin: 6px 0;
            padding-left: 8px;
            border-left: 2px solid #cbd5e1;
        }
        .notes-signature {
            display: block;
            width: 155px;
            max-height: 78px;
            margin: -4px auto 0;
            object-fit: contain;
        }
        .template-body {
            margin: 0 26px 16px;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-left: 3px solid {{ $documentTemplate['header_color'] ?? '#38B2AC' }};
            border-radius: 6px;
            background: #ffffff;
            color: #334155;
            font-size: 9px;
            line-height: 1.65;
        }
        .template-body p { margin-bottom: 7px; }
        .template-body strong,
        .template-body b { color: #0f172a; font-weight: 700; }
        .template-body ul,
        .template-body ol { margin: 4px 0 7px 18px; }
        .template-body blockquote {
            margin: 7px 0;
            padding: 6px 9px;
            border-left: 3px solid {{ $documentTemplate['header_color'] ?? '#38B2AC' }};
            background: #f8fafc;
            color: #475569;
        }
        .totals-box {
            margin: 4px 26px 0 auto;
            width: 250px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }
        .total-row {
            padding: 8px 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .total-row:last-child { border-bottom: none; background: #f8fafc; }
        .total-label {
            display: inline-block;
            width: 50%;
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .total-amount {
            display: inline-block;
            width: 48%;
            text-align: right;
            color: #0f172a;
            font-size: 10px;
            font-weight: 700;
        }
        .grand-total .total-label,
        .grand-total .total-amount {
            color: #0f172a;
            font-size: 11px;
        }
        .closing-box {
            margin: 14px 26px 0;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            color: #475569;
            font-size: 8px;
            line-height: 1.55;
        }
        .footer {
            position: fixed;
            right: 26px;
            bottom: 16px;
            left: 26px;
            color: #cbd5e1;
            font-size: 7px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            @if($logoUrl)
                <div class="tenant-logo">
                    <img src="{{ $logoUrl }}" alt="Logo">
                </div>
            @endif
            <div class="tenant-meta">
                <div class="tenant-name">{{ $tenant?->business_name ?: $tenant?->name }}</div>
                <div class="muted">
                    @if($tenant?->email) {{ $tenant->email }}<br>@endif
                    @if($tenant?->phone) Tel. {{ $tenant->phone }}@endif
                </div>
            </div>
        </div>
        <div class="header-right">
            <div class="doc-title">Presupuesto</div>
            <div class="doc-meta">
                Folio: {{ $budget->folio }}<br>
                Fecha: {{ $budget->budget_date?->format('d/m/Y') }}<br>
                Vigencia: {{ $budget->valid_until?->format('d/m/Y') ?? 'Sin vigencia' }}<br>
                Estatus: {{ $statusLabels[$budget->status] ?? $budget->status }}
            </div>
        </div>
    </div>

    <div class="summary-box">
        <div class="summary-left">
            <div class="label">Cliente</div>
            <div class="client-name">{{ $customer?->full_name ?? 'Cliente no disponible' }}</div>
            <div class="muted">
                @if($customer?->email) {{ $customer->email }} @endif
                @if($customer?->email && $customer?->phone) - @endif
                @if($customer?->phone) Tel. {{ $customer->phone }} @endif
            </div>
        </div>
        <div class="summary-right">
            <div class="label">Total presupuesto</div>
            <div class="total-value">${{ number_format((float) $budget->total, 2) }}</div>
        </div>
    </div>

    <div class="animal-section">
        <div class="animal-header">
            <div class="animal-name">{{ $documentTemplate['image_section_title'] ?? 'Detalle del presupuesto' }}</div>
            <div class="animal-subtotal">{{ $budget->animals->count() }} caballos</div>
        </div>
    </div>

    @foreach($budget->animals as $budgetAnimal)
        <div class="animal-section">
            <div class="animal-header">
                <div class="animal-name">{{ $budgetAnimal->animal?->name ?? 'Caballo no disponible' }}</div>
                <div class="animal-subtotal">${{ number_format((float) $budgetAnimal->subtotal, 2) }}</div>
            </div>
            @if($budgetAnimal->notes)
                <div class="animal-notes">{{ $budgetAnimal->notes }}</div>
            @endif
            <table>
                <thead>
                    <tr>
                                                <th class="right">Servicio</th>

                        <th class="right">Cantidad</th>
                        <th class="right">Precio presupuesto</th>
                        <th class="right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($budgetAnimal->items as $item)
                        <tr>
                                                        <td class="service-name">{{ $item->service_name_snapshot }}</td>

                            <td class="right">{{ number_format((float) $item->quantity, 2) }}</td>
                            <td class="right">${{ number_format((float) $item->price_at_budget, 2) }}</td>
                            <td class="right">${{ number_format((float) $item->subtotal, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Sin servicios registrados para este caballo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach

    @if($budget->notes || $signatureDataUri)
        <div class="notes-box">
            <div class="label">Notas generales</div>
            @if($budget->notes)
                <div class="notes-content">{!! $budget->notes !!}</div>
            @endif
            @if($signatureDataUri)
                <img class="notes-signature" src="{{ $signatureDataUri }}" alt="Firma">
            @endif
        </div>
    @endif

    <div class="totals-box">
        <div class="total-row">
            <div class="total-label">Subtotal</div>
            <div class="total-amount">${{ number_format((float) $budget->subtotal, 2) }}</div>
        </div>
        <div class="total-row grand-total">
            <div class="total-label">Total</div>
            <div class="total-amount">${{ number_format((float) $budget->total, 2) }}</div>
        </div>
    </div>

    @if(trim($documentTemplate['closing_text'] ?? '') !== '')
        <div class="closing-box">
            {{ $documentTemplate['closing_text'] }}
        </div>
    @endif

    <div class="footer">
        Documento generado automaticamente &middot; {{ $tenant?->business_name ?: $tenant?->name }} &middot; {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
