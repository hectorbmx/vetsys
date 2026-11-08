<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan pendiente de pago</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f8fafc; padding:40px;">
    <div style="max-width:600px; margin:auto; background:white; border-radius:16px; padding:40px; border:1px solid #e2e8f0;">
        <h1 style="margin-top:0; color:#0f172a;">Tu plan está pendiente de pago</h1>

        <p style="color:#475569; line-height:1.7;">
            Hola <strong>{{ $tenant->business_name ?: $tenant->name }}</strong>,
        </p>

        <p style="color:#475569; line-height:1.7;">
            Tu plan <strong>{{ $payment->plan?->name ?? 'actual' }}</strong> sigue pendiente de pago.
            Completa el pago o comunícate con Master para mantener habilitado el acceso operativo.
        </p>

        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin:25px 0; color:#334155; line-height:1.8;">
            <div><strong>Importe:</strong> ${{ number_format((float) $payment->amount, 2) }} {{ $payment->currency ?? 'MXN' }}</div>
            <div><strong>Vence:</strong> {{ $payment->period_ends_at?->format('d/m/Y') ?? 'Por confirmar' }}</div>
        </div>

        @if($paymentUrl)
            <div style="margin:30px 0;">
                <a href="{{ $paymentUrl }}"
                   style="background:#635bff; color:white; padding:14px 24px; border-radius:12px; text-decoration:none; font-weight:bold; display:inline-block;">
                    Completar pago
                </a>
            </div>
        @endif

        <p style="color:#94a3b8; font-size:13px; line-height:1.6;">
            Si ya realizaste el pago, puedes ignorar este mensaje o comunicarte con Master para verificarlo.
        </p>
    </div>
</body>
</html>
