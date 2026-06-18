<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carta de microchip - {{ $animal->name }}</title>
    <style>
        body { margin: 0; background: #f1f5f9; color: #0f172a; font-family: Arial, sans-serif; }
        main { box-sizing: border-box; width: min(800px, calc(100% - 32px)); margin: 32px auto; padding: 40px; background: white; }
        header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; }
        h1 { margin: 0; font-size: 24px; } p { margin: 6px 0; } img { display: block; max-width: 100%; max-height: 650px; margin: 32px auto; object-fit: contain; }
        .actions { text-align: center; margin: 24px; } button { padding: 12px 20px; border: 0; border-radius: 8px; background: #0f172a; color: white; font-weight: bold; cursor: pointer; }
        @media print { body { background: white; } main { width: 100%; margin: 0; padding: 24px; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Imprimir</button></div>
    <main>
        <header>
            <div><h1>Carta de microchip</h1><p>{{ $animal->tenant->name }}</p></div>
            <div><strong>{{ $animal->name }}</strong><p>Microchip: {{ $animal->microchip ?: 'Sin numero registrado' }}</p><p>Propietario: {{ $animal->customer->full_name }}</p></div>
        </header>
        <img src="{{ $microchipImageUrl }}" alt="Foto del microchip de {{ $animal->name }}">
    </main>
</body>
</html>
