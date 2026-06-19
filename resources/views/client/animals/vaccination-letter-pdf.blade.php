<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carta de vacunacion - {{ $letter->animal->name }}</title>
    <style>
        @page { margin: 42px 52px 55px; }
        body { color: #30343b; font-family: DejaVu Sans, sans-serif; font-size: 10.5px; line-height: 1.5; }
        .document-header { height: 3cm; margin: 0 -52px 24px; position: relative; }
        .letterhead { bottom: 0; left: 52px; position: absolute; right: 52px; top: 0; z-index: 2; }
        .letterhead img { max-height: 2.5cm; max-width: 68%; position: absolute; top: 0.25cm; }
        .fallback-brand { color: #fff; font-size: 17px; font-weight: bold; padding-top: 1cm; }
        .document-title { bottom: 0.35cm; color: #fff; font-size: 14px; font-weight: bold; position: absolute; right: 52px; text-align: right; z-index: 3; }
        .date { color: #64748b; margin-bottom: 32px; text-align: right; }
        h1 { color: #475569; font-size: 14px; font-weight: normal; margin: 0 0 20px; }
        .meta { line-height: 1.65; margin-bottom: 28px; }
        .content p { margin: 0 0 12px; text-align: justify; }
        .content h1, .content h2, .content h3 { color: #111827; margin: 14px 0 7px; }
        .evidence-title { border-top: 1px solid #e2e8f0; font-weight: bold; margin-top: 20px; padding-top: 14px; }
        .evidence { margin-top: 10px; text-align: center; }
        .evidence img { max-height: 200px; max-width: 78%; }
        .signature { margin-top: 35px; page-break-inside: avoid; width: 270px; }
        .signature img { display: block; max-height: 90px; max-width: 190px; }
        .signature-name { font-weight: bold; margin-top: 3px; }
        .footer { bottom: -35px; color: #94a3b8; font-size: 8px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">Carta finalizada el {{ $finalizedAt->format('d/m/Y H:i') }}</div>
    <header class="document-header" style="background-color: {{ $documentPresentation['header_color'] }}">
        <div class="letterhead">
            @if($documentPresentation['letterhead_data_uri'])
                <img src="{{ $documentPresentation['letterhead_data_uri'] }}" alt="Membrete">
            @else
                <div class="fallback-brand">{{ $documentPresentation['values']['clinic_name'] }}</div>
            @endif
        </div>
        <div class="document-title">Carta de vacunacion</div>
    </header>

    <div class="date">{{ $letter->date->translatedFormat('F Y') }}</div>
    <div class="meta">
        <div><strong>Propietario:</strong> {{ $letter->animal->customer?->full_name }}</div>
        <div><strong>Paciente:</strong> {{ $letter->animal->name }}</div>
        <div><strong>Vacuna:</strong> {{ $letter    ->vaccine_name ?: 'No especificada' }}</div>
    </div>

    <section class="content">{!! $documentPresentation['body_html'] !!}</section>

    @if($imageDataUri)
        <div class="evidence-title">{{ $documentPresentation['image_section_title'] }}</div>
        <div class="evidence"><img src="{{ $imageDataUri }}" alt="Evidencia de vacunacion"></div>
    @endif

    <div class="signature">
        @if($documentPresentation['closing_text'])<div>{{ $documentPresentation['closing_text'] }}</div>@endif
        @if($documentPresentation['signature_data_uri'])<img src="{{ $documentPresentation['signature_data_uri'] }}" alt="Firma">@endif
        <div class="signature-name">{{ $documentPresentation['values']['veterinarian_title'] }} {{ $documentPresentation['values']['veterinarian_name'] }}</div>
        @if($documentPresentation['values']['license_number'])<div>Cedula: {{ $documentPresentation['values']['license_number'] }}</div>@endif
    </div>
</body>
</html>
