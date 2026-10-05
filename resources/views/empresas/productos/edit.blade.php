@extends('layouts.app')
@section('title', 'Editar Producto')

@section('content')
    <div class="max-w-7xl mx-auto">@include('empresas.partials.alert')</div>
    @include('empresas.productos.partials.formulario', ['producto' => $producto])
@endsection

@push('scripts')
    @include('empresas.productos.partials.script', ['producto' => $producto])
@endpush
