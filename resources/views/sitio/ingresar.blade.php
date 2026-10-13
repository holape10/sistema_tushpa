@extends('sitio.base')
@section('titulo', 'Ingresar a mi sistema')
@section('robots', 'noindex, follow')

@section('contenido')
    <div class="row justify-content-center mt-4">
        <div class="col-md-7 col-lg-5">
            <div class="card tarjeta p-4 p-md-5">
                <h1 class="h3 fw-bold mb-1">Ingresar a mi sistema</h1>
                <p class="text-muted mb-4">Escribe el RUC de tu empresa o el nombre de tu sistema (ej. <b>mirestaurante</b>.{{ config('tenancy.dominio') }}).</p>
                <form method="POST" action="{{ route('sitio.ingresar.ir') }}">
                    @csrf
                    <label class="form-label" for="cuenta">RUC o subdominio</label>
                    <input id="cuenta" name="cuenta" value="{{ old('cuenta') }}" required autofocus autocomplete="organization"
                           class="form-control form-control-lg @error('cuenta') is-invalid @enderror" placeholder="20123456789 o mirestaurante">
                    @error('cuenta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn btn-primary btn-lg w-100 rounded-pill fw-semibold mt-4">Continuar <i class="fas fa-arrow-right ms-1"></i></button>
                </form>
                <hr class="my-4">
                <p class="text-center mb-0 text-muted">¿Aún no tienes cuenta?
                    <a href="{{ route('sitio.registro') }}" class="fw-semibold">Pruébalo gratis {{ config('sitio.dias_prueba') }} días</a></p>
            </div>
        </div>
    </div>
@endsection
