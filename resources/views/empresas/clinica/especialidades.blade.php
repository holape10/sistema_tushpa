@extends('layouts.app')
@section('title', 'Especialidades')
@section('content')
    @php $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-teal-500 focus:ring-teal-500'; @endphp

    <div x-data="especialidades()" class="max-w-5xl space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('clinica.index') }}" class="text-sm text-gray-500">← Historias clínicas</a>
                <h1 class="text-2xl font-extrabold text-gray-800 mt-1">🩺 Especialidades</h1>
                <p class="text-sm text-gray-500">Créalas una vez y luego solo se eligen al atender, en la agenda y en el registro de tratamientos.</p>
            </div>
            <button type="button" @click="agregar()" class="px-4 py-2 rounded-xl bg-white border border-teal-600 text-teal-700 text-sm font-bold">+ Agregar especialidad</button>
        </div>

        <div class="rounded-xl bg-sky-50 border border-sky-200 px-4 py-3 text-sm text-sky-800">
            El <b>servicio</b> es lo que se cobra al terminar la consulta (créalo en Productos, ej. CONSULTA ODONTOLOGICA S/ 50).
            <b>🦷 Odontograma</b> muestra el dibujo de los dientes · <b>🐾 Veterinaria</b> permite pacientes mascota con vacunas.
            Las que ya no uses <b>desactívalas</b>: sus pacientes y su historia se conservan.
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr><th class="px-3 py-2 text-left">Especialidad</th><th class="px-3 py-2 text-left">Servicio que se cobra</th>
                        <th class="px-3 py-2 text-center" title="Odontograma">🦷</th><th class="px-3 py-2 text-center" title="Veterinaria">🐾</th>
                        <th class="px-3 py-2 text-center">Activa</th><th class="px-3 py-2 text-right">Pacientes</th></tr>
                </thead>
                <tbody class="divide-y">
                    <template x-for="(e, i) in lista" :key="e.k">
                        <tr :class="e.activo ? '' : 'bg-gray-50 text-gray-400'">
                            <td class="px-3 py-2"><input x-model="e.esp_des" placeholder="Ej. ENDODONCIA" maxlength="100" class="{{ $in }} uppercase min-w-[180px]"></td>
                            <td class="px-3 py-2">
                                <select x-model="e.IdProducto" class="{{ $in }} min-w-[200px]">
                                    <option value="">— Ninguno —</option>
                                    @foreach ($servicios as $s)<option value="{{ $s->IdProducto }}">{{ $s->pronom }} · S/ {{ number_format($s->propun, 2) }}</option>@endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2 text-center"><input type="checkbox" x-model="e.odontograma" class="rounded text-teal-600"></td>
                            <td class="px-3 py-2 text-center"><input type="checkbox" x-model="e.veterinaria" class="rounded text-teal-600"></td>
                            <td class="px-3 py-2 text-center">
                                <template x-if="e.esp_id"><input type="checkbox" x-model="e.activo" class="rounded text-teal-600"></template>
                                <template x-if="!e.esp_id"><button type="button" @click="lista.splice(i, 1)" class="text-rose-500 text-xs font-bold" title="Quitar esta fila">✕ Quitar</button></template>
                            </td>
                            <td class="px-3 py-2 text-right text-gray-500" x-text="e.esp_id ? e.pacientes : 'nueva'"></td>
                        </tr>
                    </template>
                    <tr x-show="!lista.length"><td colspan="6" class="px-4 py-8 text-center text-gray-400">Aún no hay especialidades. Toca <b>+ Agregar especialidad</b>.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-end gap-3">
            <span x-show="cambios" x-cloak class="text-sm text-amber-600 font-semibold">Tienes cambios sin guardar</span>
            <button type="button" @click="guardar()" :disabled="ocupado" class="px-6 py-2.5 rounded-xl bg-teal-600 text-white font-bold hover:bg-teal-700 disabled:opacity-50">
                <span x-text="ocupado ? 'Guardando…' : '💾 Guardar todo'"></span>
            </button>
        </div>
    </div>

    <script>
        function especialidades() {
            let k = 1;
            const inicial = @js($especialidades);
            return {
                lista: inicial.map(e => ({ ...e, k: k++ })),
                ocupado: false,
                original: JSON.stringify(inicial),
                get cambios() { return JSON.stringify(this.lista.map(({ k, ...e }) => e)) !== this.original; },
                agregar() {
                    this.lista.push({ k: k++, esp_id: null, esp_des: '', IdProducto: '', odontograma: false, veterinaria: false, activo: true, pacientes: 0 });
                    this.$nextTick(() => { const f = this.$el.querySelectorAll('tbody input[placeholder]'); f[f.length - 1]?.focus(); });
                },
                async guardar() {
                    const vacia = this.lista.findIndex(e => !e.esp_des.trim());
                    if (vacia >= 0) { tushpaAviso('Escribe el nombre de la especialidad (fila ' + (vacia + 1) + ') o quita esa fila.', 'error'); return; }
                    this.ocupado = true;
                    try {
                        const r = await fetch('{{ route('clinica.especialidad') }}', {
                            method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({ especialidades: this.lista.map(e => ({ esp_id: e.esp_id, esp_des: e.esp_des, IdProducto: e.IdProducto || null,
                                odontograma: e.odontograma ? 1 : 0, veterinaria: e.veterinaria ? 1 : 0, activo: e.activo ? 1 : 0 })) }),
                        });
                        const j = await r.json().catch(() => ({}));
                        const mensaje = j.mensaje || Object.values(j.errors || {}).flat()[0] || 'No se pudo guardar.';
                        if (!r.ok || !j.ok) { tushpaAviso(mensaje, 'error'); return; }
                        tushpaAviso(mensaje, 'ok');
                        setTimeout(() => location.reload(), 700);
                    } finally {
                        this.ocupado = false;
                    }
                },
            };
        }
    </script>
@endsection
