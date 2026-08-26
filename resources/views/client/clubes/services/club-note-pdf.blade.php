<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Nota de club {{ $clubNote->folio }}</title>
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
        .club-name {
            color: #0f172a;
            font-size: 12px;
            font-weight: 700;
        }
        .total-value {
            color: #0f172a;
            font-size: 19px;
            font-weight: 700;
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
        .section {
            margin: 0 26px 12px;
            page-break-inside: avoid;
        }
        .section-title {
            padding: 7px 10px;
            border-left: 3px solid {{ $documentTemplate['header_color'] ?? '#38B2AC' }};
            background: #f1f5f9;
            color: #0f172a;
            font-size: 10px;
            font-weight: 700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e2e8f0;
        }
        th {
            padding: 6px 7px;
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
            padding: 7px;
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
        }
        .custom-notes {
            margin: 0 26px 12px;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-left: 3px solid {{ $documentTemplate['header_color'] ?? '#38B2AC' }};
            border-radius: 6px;
            background: #ffffff;
            color: #334155;
            font-size: 9px;
            line-height: 1.6;
        }
        .custom-notes p { margin-bottom: 7px; }
        .custom-notes p:last-child { margin-bottom: 0; }
        .custom-notes strong,
        .custom-notes b { color: #0f172a; font-weight: 700; }
        .custom-notes ul,
        .custom-notes ol { margin-left: 14px; }
        .custom-notes blockquote {
            margin: 6px 0;
            padding-left: 8px;
            border-left: 2px solid #cbd5e1;
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
            font-size: 11px;
            font-weight: 700;
        }
        .closing-box {
            margin: 14px 26px 0;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 8px;
            line-height: 1.55;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            @if($logoUrl)
                <div class="tenant-logo">
                    <img src="{{ $logoUrl }}" alt="{{ $tenant->name }}">
                </div>
            @endif
            <div class="tenant-meta">
                <div class="tenant-name">{{ $tenant->business_name ?: $tenant->name }}</div>
                <div class="muted">{{ $tenant->email ?? 'Sin correo registrado' }}</div>
                <div class="muted">{{ $tenant->phone ?? 'Sin telefono registrado' }}</div>
            </div>
        </div>
        <div class="header-right">
            <div class="doc-title">Nota de club</div>
            <div class="doc-meta">Folio: {{ $clubNote->folio }}</div>
            <div class="doc-meta">Fecha: {{ $clubNote->date_at?->format('d/m/Y') }}</div>
        </div>
    </div>

    <div class="summary-box">
        <div class="summary-left">
            <div class="label">Club</div>
            <div class="club-name">{{ $club->name }}</div>
            @if($club->description)
                <div class="muted">{{ $club->description }}</div>
            @endif
        </div>
        <div class="summary-right">
            <div class="label">Total</div>
            <div class="total-value">${{ number_format((float) $clubNote->total, 2) }}</div>
        </div>
    </div>

    @if(trim(strip_tags($documentTemplate['body_html'] ?? '')) !== '')
        <div class="template-body">
            {!! $documentTemplate['body_html'] !!}
        </div>
    @endif

    <div class="section">
        <div class="section-title">{{ $documentTemplate['image_section_title'] ?: 'Detalle de servicios' }}</div>
        <table>
            <thead>
                <tr>
                    <th style="width:46%">Servicio / Producto</th>
                    <th class="right" style="width:16%">Cantidad</th>
                    <th class="right" style="width:19%">Costo</th>
                    <th class="right" style="width:19%">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($clubNote->details as $detail)
                    <tr>
                        <td class="service-name">{{ $detail->catalogItem?->name ?? 'Concepto eliminado' }}</td>
                        <td class="right">{{ number_format((float) $detail->quantity, 0) }}</td>
                        <td class="right">${{ number_format((float) $detail->price_at_sale, 2) }}</td>
                        <td class="right">${{ number_format((float) $detail->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(trim(strip_tags($clubNote->notes_html ?? '')) !== '')
        <div class="custom-notes">
            {!! $clubNote->notes_html !!}
        </div>
    @endif

    <div class="totals-box">
        <div class="total-row">
            <div class="total-label">Total</div>
            <div class="total-amount">${{ number_format((float) $clubNote->total, 2) }}</div>
        </div>
    </div>

    @if($documentTemplate['closing_text'])
        <div class="closing-box">
            {{ $documentTemplate['closing_text'] }}
        </div>
    @endif
</body>
</html>
