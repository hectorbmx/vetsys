<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carta de microchip - {{ $animal->name }}</title>
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
        .meta { line-height: 1.65; margin-bottom: 22px; }
        .content p { margin: 0 0 12px; text-align: justify; }
        .patient-grid { margin-top: 18px; width: 100%; }
        .patient-grid td { vertical-align: top; }
        .details { line-height: 1.65; width: 35%; }
        .evidence { text-align: center; width: 65%; }
        .evidence img { max-height: 255px; max-width: 100%; }
        .evidence-title { color: #64748b; font-size: 9px; margin-bottom: 8px; }
        .signature { margin-top: 42px; page-break-inside: avoid; width: 270px; }
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
        <div class="document-title">Carta de microchip</div>
    </header>

    <div class="date">{{ $finalizedAt->translatedFormat('F Y') }}</div>
    <div class="meta">
        <div><strong>Propietario:</strong> {{ $animal->customer?->full_name }}</div>
        <div><strong>Paciente:</strong> {{ $animal->name }}</div>
    </div>

    <section class="content">{!! $documentPresentation['body_html'] !!}</section>

    <table class="patient-grid">
        <tr>
            <td class="details">
                <div><strong>Nombre:</strong> {{ $animal->name }}</div>
                <div><strong>Edad:</strong> {{ $documentPresentation['values']['age'] ?: 'No registrada' }}</div>
                <div><strong>Color:</strong> {{ $animal->color ?: 'No registrado' }}</div>
                <div><strong>Raza:</strong> {{ $documentPresentation['values']['breed'] ?: 'No registrada' }}</div>
                <div><strong>Sexo:</strong> {{ $documentPresentation['values']['sex'] }}</div>
            </td>
            <td class="evidence">
                <div class="evidence-title">{{ $documentPresentation['image_section_title'] }}</div>
                @if($imageDataUri)<img src="{{ $imageDataUri }}" alt="Evidencia del microchip">@endif
            </td>
        </tr>
    </table>

    <div class="signature">
        @if($documentPresentation['closing_text'])<div>{{ $documentPresentation['closing_text'] }}</div>@endif
        @if($documentPresentation['signature_data_uri'])<img src="{{ $documentPresentation['signature_data_uri'] }}" alt="Firma">@endif
        <div class="signature-name">{{ $documentPresentation['values']['veterinarian_title'] }} {{ $documentPresentation['values']['veterinarian_name'] }}</div>
        @if($documentPresentation['values']['license_number'])<div>Cedula: {{ $documentPresentation['values']['license_number'] }}</div>@endif
    </div>
</body>
</html>
