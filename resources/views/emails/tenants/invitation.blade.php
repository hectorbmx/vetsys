<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Invitación VetSys</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f8fafc; padding:40px;">

    <div style="max-width:600px; margin:auto; background:white; border-radius:16px; padding:40px; border:1px solid #e2e8f0;">

        <h1 style="margin-top:0; color:#0f172a;">
            Bienvenido a VetSys
        </h1>

        <p style="color:#475569; line-height:1.7;">
            Hola <strong>{{ $user->name }}</strong>,
            has sido invitado a colaborar en
            <strong>{{ $tenant->name }}</strong>.
        </p>

        <p style="color:#475569; line-height:1.7;">
            Para activar tu cuenta y crear tu contraseña,
            haz clic en el siguiente botón:
        </p>

        <div style="margin:35px 0;">
            <a href="{{ $invitationUrl }}"
               style="background:#0f172a; color:white; padding:14px 24px; border-radius:12px; text-decoration:none; font-weight:bold;">
                Activar cuenta
            </a>
        </div>

        <p style="color:#94a3b8; font-size:13px;">
            Este enlace expirará en 7 días.
        </p>

    </div>

</body>
</html>