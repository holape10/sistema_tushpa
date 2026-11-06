@extends('layouts.app')
@section('title', $proceso->descripcion)
@section('content')
<div class="max-w-xl mx-auto" x-data="avanceProceso(@js(['estado' => $proceso->estado, 'mensaje' => $proceso->mensaje]))" x-init="seguir()">
    <div class="bg-white rounded-2xl shadow-sm p-6 text-center space-y-4">
        <p class="text-xs font-bold uppercase text-gray-400">Proceso en segundo plano</p>
        <h1 class="text-xl font-extrabold text-gray-800">{{ $proceso->descripcion }}</h1>

        <template x-if="estado === 'EN COLA' || estado === 'EN PROCESO'">
            <div class="space-y-3">
                <div class="mx-auto h-12 w-12 rounded-full border-4 border-indigo-100 border-t-indigo-600 animate-spin"></div>
                <p class="font-semibold text-gray-700" x-text="estado === 'EN COLA' ? 'En espera de turno…' : 'Preparando…'"></p>
                <p class="text-sm text-gray-500">Puedes seguir trabajando en otra pestaña o volver más tarde: esta pantalla se actualiza sola.</p>
            </div>
        </template>
        <template x-if="estado === 'LISTO'">
            <div class="space-y-3">
                <p class="text-5xl">✅</p>
                <p class="font-semibold text-emerald-700" x-text="mensaje || 'Listo.'"></p>
                <p class="text-sm text-gray-500">La descarga empieza sola. Si no empezó, tócalo aquí:</p>
                <a href="{{ route('procesos.descargar', $proceso->id) }}" class="inline-flex items-center h-11 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold">⬇ Descargar</a>
                <p class="text-xs text-gray-400">El archivo se guarda {{ \App\Support\Procesos::HORAS }} horas.</p>
            </div>
        </template>
        <template x-if="estado === 'ERROR'">
            <div class="space-y-3">
                <p class="text-5xl">⚠️</p>
                <p class="font-semibold text-rose-700" x-text="mensaje || 'No se pudo terminar.'"></p>
                <button type="button" onclick="history.back()" class="h-11 px-6 rounded-xl bg-gray-100 hover:bg-gray-200 font-semibold">Volver e intentar otra vez</button>
            </div>
        </template>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('avanceProceso', (inicial) => ({
            estado: inicial.estado, mensaje: inicial.mensaje,
            // Apenas está listo, se descarga solo (una vez: si recargan la página no se vuelve a bajar)
            descargarSolo() {
                const clave = 'proceso-descargado-{{ $proceso->id }}';
                try { if (sessionStorage.getItem(clave)) return; sessionStorage.setItem(clave, '1'); } catch (e) {}
                window.location.href = @json(route('procesos.descargar', $proceso->id));
            },
            seguir() {
                if (this.estado === 'LISTO') { this.descargarSolo(); return; }
                if (this.estado === 'ERROR') return;
                setTimeout(async () => {
                    try {
                        const r = await fetch(@json(route('procesos.estado', $proceso->id)), { headers: { Accept: 'application/json' } });
                        if (r.ok) { const d = await r.json(); this.estado = d.estado; this.mensaje = d.mensaje; }
                    } catch (e) { /* sin conexión: se vuelve a intentar */ }
                    this.seguir();
                }, 3000);
            },
        }));
    });
</script>
@endpush
