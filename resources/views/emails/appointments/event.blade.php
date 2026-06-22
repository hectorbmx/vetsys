<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $mailData['subject'] }}</title>
</head>
<body style="font-family:Arial,sans-serif;background:#f8fafc;padding:32px;color:#172033;">
    <div style="max-width:620px;margin:auto;background:#fff;border:1px solid #dfe5ec;border-radius:16px;padding:32px;">
        <p style="margin:0 0 8px;color:#64748b;font-size:13px;font-weight:bold;text-transform:uppercase;">
            {{ $mailData['tenant_name'] }}
        </p>
        <h1 style="margin:0 0 20px;font-size:24px;color:#172033;">{{ $mailData['title'] }}</h1>
        <p style="line-height:1.6;">Hola {{ $mailData['recipient_name'] }},</p>
        <p style="line-height:1.6;">{{ $mailData['intro'] }}</p>

        <table style="width:100%;margin:24px 0;border-collapse:collapse;">
            <tr><td style="padding:9px 0;color:#64748b;">Paciente</td><td style="padding:9px 0;text-align:right;font-weight:bold;">{{ $mailData['animal_name'] }}</td></tr>
            <tr><td style="padding:9px 0;color:#64748b;">Servicio</td><td style="padding:9px 0;text-align:right;font-weight:bold;">{{ $mailData['service_name'] }}</td></tr>
            <tr><td style="padding:9px 0;color:#64748b;">Veterinario</td><td style="padding:9px 0;text-align:right;font-weight:bold;">{{ $mailData['doctor_name'] }}</td></tr>
            <tr><td style="padding:9px 0;color:#64748b;">Fecha y hora</td><td style="padding:9px 0;text-align:right;font-weight:bold;">{{ $mailData['appointment_at'] }}</td></tr>
            <tr><td style="padding:9px 0;color:#64748b;">Zona horaria</td><td style="padding:9px 0;text-align:right;font-weight:bold;">{{ $mailData['timezone'] }}</td></tr>
        </table>

        @if ($mailData['visible_message'])
            <div style="margin:20px 0;padding:16px;background:#f1f5f9;border-radius:12px;line-height:1.6;">
                {{ $mailData['visible_message'] }}
            </div>
        @endif

        <div style="margin:30px 0;">
            <a href="{{ $mailData['url'] }}" style="display:inline-block;background:#17345f;color:#fff;padding:13px 20px;border-radius:11px;text-decoration:none;font-weight:bold;">
                Ver cita
            </a>
        </div>

        <p style="margin:24px 0 0;color:#64748b;font-size:12px;line-height:1.6;">
            Este correo contiene un resumen de la reserva. Consulta la aplicacion para ver la informacion actualizada.
        </p>
    </div>
</body>
</html>
