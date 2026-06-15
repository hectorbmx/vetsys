@extends('layouts.app-store')

@section('title', 'Soporte')
@section('description', 'Información de soporte para usuarios de la aplicación VetSys.')

@section('content')
    <p class="eyebrow">Ayuda y soporte</p>
    <h1>Estamos para ayudarte.</h1>

    <p>
        Si tienes preguntas sobre VetSys, necesitas ayuda para acceder a tu cuenta
        o deseas reportar un problema con la aplicación, comunícate con nuestro equipo.
    </p>

    <div class="contact">
        <p><strong>Correo de soporte</strong></p>
        <p><a href="mailto:soporte@hdoc.vet">soporte@hdoc.vet</a></p>
        <p>Incluye una descripción del problema y el correo asociado a tu cuenta.</p>
    </div>

    <h2>Horario de atención</h2>
    <p>Lunes a viernes, de 9:00 a 18:00, hora del centro de México.</p>

    <h2>Tiempo de respuesta</h2>
    <p>Normalmente respondemos dentro de 1 a 2 días hábiles.</p>
@endsection
