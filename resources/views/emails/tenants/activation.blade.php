<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Activacion VetSys</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f8fafc; padding:40px;">
    <div style="max-width:600px; margin:auto; background:white; border-radius:16px; padding:40px; border:1px solid #e2e8f0;">
        <h1 style="margin-top:0; color:#0f172a;">Bienvenido a VetSys</h1>

        <p style="color:#475569; line-height:1.7;">
            Hola <strong>{{ $tenant->business_name ?: $tenant->name }}</strong>,
            tu cuenta fue creada y esta lista para activarse.
        </p>

        <p style="color:#475569; line-height:1.7;">
            Puedes crear tu usuario administrador dando clic en este boton:
        </p>

        <div style="margin:35px 0;">
            <a href="{{ $activationUrl }}"
               style="background:#0f172a; color:white; padding:14px 24px; border-radius:12px; text-decoration:none; font-weight:bold;">
                Activar cuenta
            </a>
        </div>

        <p style="color:#475569; line-height:1.7;">
            Si no tienes acceso al correo, tambien puedes entrar a /activar-cuenta y usar este codigo:
        </p>

        <div style="font-size:28px; letter-spacing:8px; font-weight:bold; color:#0f172a; margin:20px 0;">
            {{ $activationCode }}
        </div>

        <p style="color:#94a3b8; font-size:13px;">
            Este enlace y codigo expiran en 7 dias.
        </p>
    </div>
</body>
</html>
