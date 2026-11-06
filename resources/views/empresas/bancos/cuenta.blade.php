@extends('layouts.app')
@section('title', 'Cuenta '.$cuenta->banco)
@section('content')
<div class="max-w-5xl mx-auto space-y-4">
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif
    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5">
        <a href="{{ route('bancos.show', $cuenta->id) }}" class="text-xs font-semibold text-indigo-700 hover:underline">← {{ $cuenta->banco }} {{ $cuenta->numero }}</a>
        <h1 class="text-xl font-extrabold text-gray-800 mb-4">Editar cuenta bancaria</h1>
        @include('empresas.bancos._form_cuenta')
    </div>
</div>
@endsection
