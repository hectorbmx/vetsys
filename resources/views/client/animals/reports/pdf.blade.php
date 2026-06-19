<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 45px 50px 55px; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 10.5px; line-height: 1.45; }
        .document-header { height: 3cm; margin: 0 -50px 24px; position: relative; }
        .letterhead { bottom: 0; left: 50px; position: absolute; right: 50px; top: 0; z-index: 2; }
        .letterhead img { max-height: 2.5cm; max-width: 68%; position: absolute; top: 0.25cm; }
        .fallback-brand { color: #fff; font-size: 17px; font-weight: bold; padding-top: 1cm; }
        .document-title { bottom: 0.35cm; color: #fff; font-size: 14px; font-weight: bold; position: absolute; right: 50px; text-align: right; z-index: 3; }
        .date-line { color: #6b7280; margin-bottom: 26px; text-align: right; }
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
        .signature { margin-top: 30px; page-break-inside: avoid; width: 260px; }
        .signature img { display: block; max-height: 85px; max-width: 180px; }
        .signature-name { font-weight: bold; margin-top: 4px; }
        .footer { bottom: -35px; color: #64748b; font-size: 8.5px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">Reporte clinico generado por {{ $report->tenant->name }} &middot; Documento finalizado el {{ $finalizedAt->format('d/m/Y H:i') }}</div>

    <header class="document-header" style="background-color: {{ $documentPresentation['header_color'] }}">
        <div class="letterhead">
            @if($documentPresentation['letterhead_data_uri'])
                <img src="{{ $documentPresentation['letterhead_data_uri'] }}" alt="Membrete">
            @else
                <div class="fallback-brand">{{ $documentPresentation['values']['clinic_name'] }}</div>
            @endif
        </div>
        <div class="document-title">{{ $report->title }}</div>
    </header>
    <div class="date-line">{{ $report->report_date->translatedFormat('F Y') }}</div>

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

    @if($documentPresentation['body_html'])
        <section class="content">{!! $documentPresentation['body_html'] !!}</section>
    @endif
    <section class="content">{!! $report->content_html !!}</section>

    @if($imageData->isNotEmpty())
        <div class="images-title">{{ $documentPresentation['image_section_title'] }}</div>
        @foreach($imageData as $image)
            <div class="image">
                <img src="{{ $image['data_uri'] }}" alt="Imagen clinica">
                @if($image['name'])<div class="caption">{{ $image['name'] }}</div>@endif
            </div>
        @endforeach
    @endif

    <div class="signature">
        @if($documentPresentation['closing_text'])
            <div>{{ $documentPresentation['closing_text'] }}</div>
        @endif
        @if($documentPresentation['signature_data_uri'])
            <img src="{{ $documentPresentation['signature_data_uri'] }}" alt="Firma">
        @endif
        <div class="signature-name">{{ $documentPresentation['values']['veterinarian_title'] }} {{ $documentPresentation['values']['veterinarian_name'] }}</div>
        @if($documentPresentation['values']['license_number'])
            <div>Cedula: {{ $documentPresentation['values']['license_number'] }}</div>
        @endif
    </div>
</body>
</html>
