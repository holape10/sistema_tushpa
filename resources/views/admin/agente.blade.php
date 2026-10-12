@extends('admin.layout')
@section('title', 'Agente de impresión')

@section('content')
    <h1 class="text-xl font-bold mb-4">Agente de impresión</h1>

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            @foreach ($errors->all() as $error)<div>• {{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <p class="font-bold">Instalador actual</p>
            @if ($existe)
                <p class="mt-2 text-sm text-emerald-700 font-semibold">✔ Instalar_Agente_TUSHPA.exe · {{ round($tamano / 1048576, 1) }} MB</p>
                <p class="text-sm text-slate-500">Subido el {{ $fecha->format('d/m/Y H:i') }}. Las empresas lo descargan en su sistema: Impresoras › "Descargar instalador".</p>
            @else
                <p class="mt-2 text-sm text-amber-700 font-semibold">⚠ Aún no se subió. Mientras tanto, las empresas solo pueden descargar el agente en ZIP.</p>
            @endif

            <form method="POST" action="{{ route('admin.agente.subir') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3"
                  x-data="{ n: false, enviando: false }" @submit="enviando = true">
                @csrf
                <input type="file" name="instalador" accept=".exe" required class="text-sm" @change="n = $event.target.files.length > 0">
                <button class="px-4 h-10 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold disabled:opacity-50" :disabled="!n || enviando"
                        x-text="enviando ? 'Subiendo…' : '{{ $existe ? 'Reemplazar instalador' : 'Subir instalador' }}'"></button>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-5 text-sm text-slate-600 space-y-2">
            <p class="font-bold text-slate-800">Cómo se genera el instalador</p>
            <p>1. En tu PC con Inno Setup 6, abre la carpeta del proyecto <span class="font-mono">deploy\agente</span> y haz doble clic en <b>construir.bat</b>.
                Usa el PHP portátil de tu <span class="font-mono">C:\sistema</span>.</p>
            <p>2. Sube aquí el archivo <span class="font-mono">deploy\agente\salida\Instalar_Agente_TUSHPA.exe</span>.</p>
            <p class="font-bold text-slate-800 pt-2">Qué hace en la PC del cliente</p>
            <p>Detiene el agente si ya había uno, copia todo a <span class="font-mono">C:\sistema</span> (conserva su config.ini), trae la clave de la empresa con el código
                que va en el nombre del archivo, lo deja en el Inicio de Windows y lo arranca. El cliente solo hace doble clic.</p>
        </div>
    </div>
@endsection
