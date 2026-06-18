<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 45px 50px 55px; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 10.5px; line-height: 1.45; }
        .header { border-bottom: 3px solid #111827; padding-bottom: 16px; margin-bottom: 22px; }
        .brand { display: inline-block; vertical-align: middle; width: 62%; }
        .brand img { max-height: 55px; max-width: 155px; vertical-align: middle; }
        .brand-name { display: inline-block; margin-left: 12px; font-size: 17px; font-weight: bold; vertical-align: middle; }
        .place { display: inline-block; width: 37%; text-align: right; color: #6b7280; vertical-align: middle; }
        h1 { color: #0f766e; font-size: 19px; margin: 0 0 8px; }
        .meta { width: 100%; margin-bottom: 22px; border-collapse: collapse; }
        .meta td { padding: 5px 8px 5px 0; vertical-align: top; }
        .label { color: #9a7b22; font-weight: bold; }
        .content h1, .content h2, .content h3 { color: #111827; margin: 14px 0 7px; }
        .content h1 { font-size: 16px; } .content h2 { font-size: 14px; } .content h3 { font-size: 12px; }
        .content p { margin: 0 0 10px; text-align: justify; }
        .content blockquote { border-left: 3px solid #94a3b8; color: #475569; margin: 10px 0; padding-left: 12px; }
        .content ul, .content ol { margin: 7px 0 10px 20px; }
        .ql-align-center { text-align: center !important; } .ql-align-right { text-align: right !important; } .ql-align-justify { text-align: justify !important; }
        .images-title { border-top: 1px solid #cbd5e1; font-size: 12px; font-weight: bold; margin-top: 26px; padding-top: 14px; }
        .image { page-break-inside: avoid; margin-top: 16px; text-align: center; }
        .image img { max-height: 470px; max-width: 100%; }
        .caption { color: #64748b; font-size: 9px; margin-top: 5px; }
        .footer { bottom: -35px; color: #64748b; font-size: 8.5px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">Reporte clinico generado por {{ $report->tenant->name }} &middot; Documento finalizado el {{ $finalizedAt->format('d/m/Y H:i') }}</div>

    <header class="header">
        <div class="brand">
            @if($logoDataUri)<img src="{{ $logoDataUri }}" alt="Logo">@endif
            <span class="brand-name">{{ $report->tenant->business_name ?: $report->tenant->name }}</span>
        </div>
        <div class="place">{{ now()->translatedFormat('F Y') }}</div>
    </header>

    <h1>{{ $report->title }}</h1>
    <table class="meta">
        <tr>
            <td><span class="label">Paciente:</span> {{ $report->animal->name }}</td>
            <td><span class="label">Especie:</span> {{ $report->animal->animalType?->name ?: 'No registrada' }}</td>
        </tr>
        <tr>
            <td><span class="label">Propietario o referente:</span> {{ $report->animal->customer?->full_name }}</td>
            <td><span class="label">Fecha:</span> {{ $report->report_date->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td colspan="2"><span class="label">Veterinario:</span> {{ $report->author?->name ?? 'No registrado' }}</td>
        </tr>
    </table>

    <section class="content">{!! $report->content_html !!}</section>

    @if($imageData->isNotEmpty())
        <div class="images-title">Imagenes de referencia significativas</div>
        @foreach($imageData as $image)
            <div class="image">
                <img src="{{ $image['data_uri'] }}" alt="Imagen clinica">
                @if($image['name'])<div class="caption">{{ $image['name'] }}</div>@endif
            </div>
        @endforeach
    @endif
</body>
</html>
