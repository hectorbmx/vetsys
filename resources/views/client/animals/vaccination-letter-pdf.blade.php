@php
    $sexLabels = [
        'male' => 'Macho',
        'female' => 'Hembra',
        'unknown' => 'Desconocido',
    ];

    $ageText = 'Sin fecha';
    if ($animal->birthdate) {
        $years = $animal->birthdate->diffInYears(now());
        $ageText = $years === 1 ? '1 ano' : $years . ' anos';
    }

    $animalType = $animal->animalType->name ?? 'Caballo';
    $tenantName = $tenant->business_name ?: $tenant->name;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Carta Vacunacion - {{ $animal->name }}</title>
    <style>
        @page { margin: 0; size: letter portrait; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #ffffff;
            color: #000000;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }
        .page {
            width: 100%;
            min-height: 100%;
            padding: 0;
            background: #ffffff;
        }
        .header {
            height: 86px;
            background: #000000;
            color: #ffffff;
            border-bottom: 3px solid #9fb5ae;
            padding: 30px 42px 0 42px;
        }
        .title {
            font-family: DejaVu Serif, serif;
            font-size: 15px;
            letter-spacing: .02em;
            display: inline-block;
            width: 65%;
        }
        .brand {
            display: inline-block;
            width: 33%;
            text-align: right;
            vertical-align: top;
            font-size: 8px;
            letter-spacing: .22em;
            line-height: 1.2;
        }
        .brand-mark {
            width: 34px;
            height: 34px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            display: inline-block;
            text-align: center;
            line-height: 31px;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .brand-logo {
            max-width: 90px;
            max-height: 42px;
            display: inline-block;
            margin-bottom: 2px;
        }
        .content {
            padding: 34px 42px 0 42px;
        }
        .date-line {
            text-align: right;
            margin: 0 0 30px 0;
            font-size: 12px;
        }
        .intro-title {
            font-family: DejaVu Serif, serif;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 24px;
        }
        .name-row {
            width: 300px;
            border-collapse: collapse;
            margin-bottom: 7px;
        }
        .name-row td {
            height: 20px;
            padding: 2px 8px;
            line-height: 15px;
        }
        .name-label {
            width: 122px;
            background: #000000;
            color: #ffffff;
            text-align: right;
            font-weight: bold;
            font-size: 12px;
        }
        .name-value {
            width: 120px;
            border: 1px solid #d7c7c7;
            color: #8b8b8b;
            font-weight: bold;
            text-align: center;
            font-size: 12px;
        }
        .paragraph {
            width: 480px;
            margin: 18px auto 16px auto;
            text-align: center;
            line-height: 1.45;
            font-size: 12px;
        }
        .main-table {
            width: 430px;
            margin: 0 auto;
            border-collapse: collapse;
        }
        .main-table td {
            vertical-align: top;
            padding: 0;
        }
        .date-badge {
            width: 122px;
            height: 23px;
            background: #a20000;
            color: #ffffff;
            font-weight: bold;
            font-size: 11px;
            text-align: center;
            line-height: 23px;
            margin: 0 auto;
        }
        .info-table {
            width: 138px;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .info-table td {
            height: 22px;
            padding: 2px 6px;
            line-height: 16px;
            border: 1px solid #d7c7c7;
            font-size: 11px;
        }
        .info-label {
            width: 45px;
            background: #9fc8b4;
            color: #ffffff;
            font-weight: bold;
            text-align: right;
        }
        .info-value {
            width: 91px;
            color: #000000;
        }
        .vaccine-image-wrap {
            width: 210px;
            height: 150px;
            border: 4px solid #eedb00;
            background: #f8fafc;
            margin-top: 0;
        }
        .vaccine-image {
            width: 210px;
            height: 150px;
        }
        .footer-zone {
            margin-top: 36px;
            padding-left: 12px;
            position: relative;
            min-height: 150px;
        }
        .doctor-copy {
            width: 220px;
            font-size: 12px;
            line-height: 1.8;
        }
        .signature-slot {
            position: absolute;
            left: 130px;
            top: -8px;
            width: 160px;
            height: 95px;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="title">CARTA VACUNACION</div>
            <div class="brand">
                @if($tenantLogoDataUri)
                    <img src="{{ $tenantLogoDataUri }}" class="brand-logo" alt="{{ $tenantName }}">
                @else
                    <div class="brand-mark">{{ strtoupper(substr($tenantName, 0, 1)) }}</div><br>
                    {{ strtoupper($tenantName) }}<br>
                    <span style="font-size:6px; letter-spacing:.12em;">Equine Sports Med</span>
                @endif
            </div>
        </div>

        <div class="content">
            <div class="date-line">Guadalajara Mexico, .{{ $generatedDate }}</div>

            <div class="intro-title">Carta de Vacunacion.</div>

            <table class="name-row">
                <tr>
                    <td class="name-label">PROPIETARIO:</td>
                    <td class="name-value">{{ $customer->full_name ?? 'Sin propietario' }}</td>
                </tr>
            </table>
            <table class="name-row">
                <tr>
                    <td class="name-label">{{ strtoupper($animalType) }}:</td>
                    <td class="name-value">{{ $animal->name }}</td>
                </tr>
            </table>

            <div class="paragraph">
                Por medio de la presente hago constar que el caballo {{ $animal->name }} esta actual en
                su calendario de medicina preventiva, siendo la ultima vacuna de influenza aplicada.
            </div>

            <table class="main-table">
                <tr>
                    <td style="width: 150px;"></td>
                    <td style="width: 242px;">
                        <div class="date-badge">{{ $letter->date->translatedFormat('d F Y') }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="width: 150px; padding-top: 10px;">
                        <table class="info-table">
                            <tr>
                                <td class="info-label">Edad:</td>
                                <td class="info-value">{{ $ageText }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Color:</td>
                                <td class="info-value">{{ $animal->color ?: 'No registrado' }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Raza:</td>
                                <td class="info-value">{{ $animalType }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Sexo:</td>
                                <td class="info-value">{{ $sexLabels[$animal->sex] ?? 'Desconocido' }}</td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 242px;">
                        <div class="vaccine-image-wrap">
                            @if($imageDataUri)
                                <img src="{{ $imageDataUri }}" width="210" height="150" class="vaccine-image" alt="Carta de vacunacion">
                            @endif
                        </div>
                    </td>
                </tr>
            </table>

            <div class="footer-zone">
                <div class="doctor-copy">
                    Cualquier duda estoy a sus ordenes<br>
                    Mvz.{{ auth()->user()->name ?? $tenantName }}<br>
                    FEM VE0089<br>
                    FEI 10176347
                </div>
                <div class="signature-slot"></div>
            </div>
        </div>
    </div>
</body>
</html>
