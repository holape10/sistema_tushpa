@extends('admin.layout')
@section('title', 'Logos web')

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <h1 class="text-xl font-bold">Logos de clientes</h1>
        <span class="text-sm text-slate-500">Se muestran en {{ config('tenancy.dominio') }} › “Empresas que confían en nosotros” ({{ count($logos) }})</span>
        <a href="{{ config('tenancy.esquema') }}://{{ config('tenancy.dominio') }}/#clients" target="_blank" class="ml-auto text-sm font-semibold text-indigo-700 hover:underline">Ver en la web ↗</a>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            @foreach ($errors->all() as $error)<div>• {{ $error }}</div>@endforeach
        </div>
    @endif

    {{-- Subir: se pueden elegir varios a la vez; se achican solos --}}
    <form method="POST" action="{{ route('admin.logos.subir') }}" enctype="multipart/form-data"
          class="bg-white rounded-2xl shadow-sm p-5 mb-6 flex flex-wrap items-center gap-4"
          x-data="{ n: 0, enviando: false }" @submit="enviando = true">
        @csrf
        <label class="flex-1 min-w-[240px] cursor-pointer rounded-xl border-2 border-dashed border-slate-300 hover:border-indigo-400 px-4 py-5 text-center">
            <input type="file" name="logos[]" accept="image/png,image/jpeg,image/webp" multiple required class="hidden" @change="n = $event.target.files.length">
            <span class="block font-semibold text-slate-700" x-text="n ? n + (n === 1 ? ' imagen elegida' : ' imágenes elegidas') : '📁 Elegir logos (puedes elegir varios)'"></span>
            <span class="block text-xs text-slate-400 mt-1">PNG, JPG o WEBP, hasta 5 MB cada uno. Se guardan livianos automáticamente.</span>
        </label>
        <button class="px-5 h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold disabled:opacity-50" :disabled="!n || enviando"
                x-text="enviando ? 'Subiendo…' : 'Subir a la web'"></button>
    </form>

    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
        @forelse ($logos as $archivo)
            <div class="bg-white rounded-2xl shadow-sm p-3 flex flex-col items-center gap-2">
                <div class="h-24 w-full flex items-center justify-center">
                    <img src="{{ \App\Support\LogosClientes::url($archivo) }}" alt="" class="max-h-24 max-w-full object-contain">
                </div>
                <form method="POST" action="{{ route('admin.logos.eliminar') }}" onsubmit="return confirm('¿Quitar este logo de la web?')">
                    @csrf
                    <input type="hidden" name="archivo" value="{{ $archivo }}">
                    <button class="text-xs font-semibold text-rose-600 hover:text-rose-700">Quitar</button>
                </form>
            </div>
        @empty
            <div class="col-span-full bg-white rounded-2xl shadow-sm p-10 text-center text-slate-400">Aún no hay logos. Sube el primero arriba.</div>
        @endforelse
    </div>
@endsection
