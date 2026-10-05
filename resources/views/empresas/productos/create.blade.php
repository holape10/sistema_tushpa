@extends('layouts.app')
@section('title', 'Nuevo Producto')

@section('content')
    <div class="max-w-7xl mx-auto">@include('empresas.partials.alert')</div>
    @include('empresas.productos.partials.formulario', ['producto' => null])
@endsection

@push('scripts')
    @include('empresas.productos.partials.script', ['producto' => null])
@endpush
