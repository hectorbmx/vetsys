@extends('layouts.app-store')

@section('title', 'Derechos de autor')
@section('description', 'Informacion sobre los derechos de autor de VetSys.')

@section('content')
    <p class="eyebrow">Derechos de autor</p>
    <h1>Todos los derechos reservados.</h1>

    <p>
        &copy; {{ date('Y') }} VetSys. El contenido, diseno, software, imagenes,
        textos y demas elementos relacionados con VetSys estan protegidos por
        las leyes aplicables de propiedad intelectual.
    </p>

    <h2>Uso autorizado</h2>
    <p>
        No esta permitida la reproduccion, distribucion, modificacion o uso
        comercial de estos materiales sin autorizacion previa y por escrito.
    </p>

    <div class="contact">
        <p><strong>Consultas sobre derechos de autor</strong></p>
        <p><a href="mailto:soporte@hdoc.vet">soporte@hdoc.vet</a></p>
    </div>
@endsection
