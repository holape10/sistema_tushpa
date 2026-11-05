{{-- Portada del dominio de una marca: la empresa escribe su RUC o el nombre de su sistema y entra a su dirección --}}
@extends('marca.base')
@section('titulo', 'Ingresar')

@section('contenido')
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card tarjeta p-4 p-sm-5 mt-3">
                <h1 class="h4 fw-bold mb-1">Ingresa a tu sistema</h1>
                <p class="text-muted mb-4">Escribe el RUC de tu empresa o el nombre de tu sistema (ej. <b>miempresa</b>.{{ config('marca.dominio') }}).</p>
                @if ($error)<div class="alert alert-warning py-2 small">{{ $error }}</div>@endif
                <form method="GET" action="/">
                    <label class="form-label" for="cuenta">RUC o nombre de tu sistema</label>
                    <input id="cuenta" name="cuenta" value="{{ $cuenta }}" required maxlength="60" autocomplete="off" autofocus class="form-control mb-3" placeholder="20123456789">
                    <button class="btn btn-lg w-100 text-white fw-semibold rounded-pill" style="background: {{ config('marca.color') }}">Ingresar</button>
                </form>
            </div>
        </div>
    </div>
@endsection
