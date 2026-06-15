<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <meta name="description" content="@yield('description')">
    <title>@yield('title') | VetSys</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #0f172a;
            background: #f5f7fb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 48px 20px;
            display: grid;
            place-items: center;
        }

        main {
            width: min(100%, 720px);
            padding: clamp(28px, 6vw, 56px);
            border: 1px solid #e2e8f0;
            border-radius: 28px;
            background: #ffffff;
            box-shadow: 0 24px 70px rgb(15 23 42 / 10%);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 36px;
            color: #475569;
            font-size: 14px;
            font-weight: 800;
        }

        .brand-mark {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            color: #ffffff;
            background: #0f766e;
        }

        .eyebrow {
            margin: 0 0 10px;
            color: #0f766e;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(34px, 7vw, 54px);
            line-height: 1.05;
            letter-spacing: -.04em;
        }

        h2 {
            margin: 34px 0 10px;
            font-size: 18px;
        }

        p,
        li {
            color: #475569;
            font-size: 16px;
            line-height: 1.7;
        }

        ul {
            padding-left: 22px;
        }

        .contact {
            margin-top: 30px;
            padding: 22px;
            border-radius: 18px;
            background: #f0fdfa;
        }

        .contact p {
            margin: 0 0 12px;
        }

        .contact p:last-child {
            margin-bottom: 0;
        }

        a {
            color: #0f766e;
            font-weight: 800;
            text-underline-offset: 3px;
        }

        footer {
            margin-top: 38px;
            padding-top: 22px;
            border-top: 1px solid #e2e8f0;
            color: #94a3b8;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <main>
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">V</span>
            <span>VetSys</span>
        </div>

        @yield('content')

        <footer>&copy; {{ date('Y') }} VetSys. Todos los derechos reservados.</footer>
    </main>
</body>
</html>
