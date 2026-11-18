@extends('layouts.app')
@section('title', 'Diseño del ticket')

@section('content')
@php
    $P = \App\Support\Impresion\PlantillaTicket::class;
    $sel = 'rounded-lg border-gray-300 text-xs py-1';
@endphp
<div class="max-w-7xl mx-auto" x-data="disenoTicket()" x-init="pedirVista()">
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="{{ route('impresion.index') }}" class="h-9 px-3 rounded-xl bg-white shadow-sm text-sm font-semibold flex items-center">← Impresoras</a>
        <div class="flex-1">
            <h1 class="text-xl font-bold text-gray-800">Diseño del ticket · {{ $imp->descripcion }}</h1>
            <p class="text-sm text-gray-500">Mira cómo sale el comprobante <b>sin imprimir nada</b>. Con «Personalizado» lo armas como era tu ticket de antes.</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-[1fr_auto] gap-5 items-start">
        {{-- Opciones y editor --}}
        <form method="POST" action="{{ route('impresion.diseno.guardar', $imp->Id) }}" class="space-y-4">
            @csrf
            <input type="hidden" name="plantilla" :value="JSON.stringify(partes)">
            <section class="bg-white rounded-2xl shadow-sm p-4 grid sm:grid-cols-3 gap-3">
                <label class="text-sm font-semibold text-gray-600">Diseño
                    <select name="diseno" x-model="diseno" @change="pedirVista()" class="mt-1 block w-full rounded-xl border-gray-300 text-sm">
                        <option value="CLASICO">Clásico</option>
                        <option value="COMPACTO">Pequeño y compacto</option>
                        <option value="EN_LINEA">En una línea</option>
                        <option value="MODERNO">Moderno</option>
                        <option value="PERSONALIZADO">Personalizado (lo armas tú)</option>
                    </select></label>
                <label class="text-sm font-semibold text-gray-600">Papel
                    <select name="columnas" x-model.number="columnas" @change="pedirVista()" class="mt-1 block w-full rounded-xl border-gray-300 text-sm">
                        <option value="42">80 mm (42 caracteres)</option>
                        <option value="48">80 mm (48 caracteres, letra pequeña)</option>
                        <option value="32">58 mm (32 caracteres)</option>
                    </select></label>
                <label class="text-sm font-semibold text-gray-600">Ver con
                    <select x-model.number="ejemplo" @change="pedirVista()" class="mt-1 block w-full rounded-xl border-gray-300 text-sm">
                        @forelse ($ejemplos as $e)
                            <option value="{{ $e['id'] }}">La última {{ mb_strtolower($e['nombre']) }}</option>
                        @empty
                            <option value="">(aún no hay ventas)</option>
                        @endforelse
                    </select></label>
            </section>

            <section x-show="diseno === 'PERSONALIZADO'" x-cloak class="bg-white rounded-2xl shadow-sm">
                <header class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100">
                    <h2 class="flex-1 font-bold text-gray-800">Partes del ticket <span class="text-xs font-normal text-gray-400">(de arriba hacia abajo; usa ▲ ▼ para moverlas)</span></h2>
                    <button type="button" @click="agregar('texto')" class="px-3 h-8 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold">+ Texto libre</button>
                    <button type="button" @click="agregar('separador')" class="px-3 h-8 rounded-lg bg-slate-100 text-slate-700 text-xs font-bold">+ Línea separadora</button>
                    <button type="button" @click="if (confirm('¿Volver al diseño de partida? Se pierde lo que armaste (si no lo guardaste).')) { partes = JSON.parse(JSON.stringify(inicial)); pedirVista(); }"
                            class="px-3 h-8 rounded-lg bg-white ring-1 ring-gray-200 text-gray-600 text-xs font-semibold">Empezar de nuevo</button>
                </header>
                <ul class="divide-y divide-gray-100">
                    <template x-for="(p, i) in partes" :key="i">
                        <li class="px-4 py-2.5 flex flex-wrap items-center gap-2 text-sm" :class="!p.visible && 'bg-gray-50 opacity-60'">
                            <span class="flex flex-col">
                                <button type="button" @click="mover(i, -1)" :disabled="i === 0" class="text-gray-400 hover:text-indigo-600 disabled:opacity-20 leading-none">▲</button>
                                <button type="button" @click="mover(i, 1)" :disabled="i === partes.length - 1" class="text-gray-400 hover:text-indigo-600 disabled:opacity-20 leading-none">▼</button>
                            </span>
                            <label class="flex items-center gap-2 w-56 font-semibold text-gray-700 cursor-pointer">
                                <input type="checkbox" x-model="p.visible" @change="pedirVista()" class="rounded text-indigo-600">
                                <span x-text="nombres[p.tipo]"></span></label>
                            <select x-model="p.alinear" @change="pedirVista()" class="{{ $sel }}" title="Alineación">
                                <option value="izq">⟸ Izquierda</option><option value="centro">⟺ Centro</option><option value="der">⟹ Derecha</option>
                            </select>
                            <select x-model="p.tamano" @change="pedirVista()" class="{{ $sel }}" title="Tamaño de letra">
                                @foreach ($P::TAMANOS as $k => $n)<option value="{{ $k }}">{{ $n }}</option>@endforeach
                            </select>
                            <label class="flex items-center gap-1 text-xs font-bold"><input type="checkbox" x-model="p.negrita" @change="pedirVista()" class="rounded"> Negrita</label>
                            <template x-if="p.tipo === 'productos'">
                                <select x-model="p.formato" @change="pedirVista()" class="{{ $sel }}">
                                    @foreach ($P::FORMATOS_PRODUCTOS as $k => $n)<option value="{{ $k }}">{{ $n }}</option>@endforeach
                                </select>
                            </template>
                            <template x-if="p.tipo === 'separador'">
                                <select x-model="p.caracter" @change="pedirVista()" class="{{ $sel }}">
                                    <option value="-">-----</option><option value="=">=====</option><option value="*">*****</option><option value="_">_____</option><option value=".">.....</option><option value=" ">(en blanco)</option>
                                </select>
                            </template>
                            <template x-if="repetibles.includes(p.tipo)">
                                <button type="button" @click="partes.splice(i, 1); pedirVista()" class="ml-auto text-xs font-semibold text-rose-600 hover:underline">Quitar</button>
                            </template>
                            <template x-if="p.tipo === 'texto'">
                                <textarea x-model="p.texto" @input.debounce.500ms="pedirVista()" rows="2" maxlength="500" placeholder="Escribe el texto (cada renglón sale en una línea)"
                                          class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                            </template>
                        </li>
                    </template>
                </ul>
            </section>

            <div class="flex justify-end gap-2">
                <a href="{{ route('impresion.index') }}" class="px-5 h-10 inline-flex items-center rounded-xl border border-gray-300 text-sm font-semibold text-gray-700">Volver</a>
                <button class="px-6 h-10 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">Guardar diseño</button>
            </div>
            <p class="text-xs text-gray-400 text-right">Se usa en esta ticketera desde la próxima venta. La línea de quién desarrolló el sistema va siempre al final.</p>
        </form>

        {{-- Vista previa --}}
        <aside class="lg:sticky lg:top-4">
            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Vista previa <span x-show="cargando" class="normal-case font-normal text-indigo-600">· actualizando…</span></p>
            <div class="max-h-[85vh] overflow-y-auto rounded-xl bg-gray-100 p-4" x-html="html"></div>
        </aside>
    </div>
</div>

<script>
    function disenoTicket() {
        return {
            diseno: @js($imp->diseno ?: 'CLASICO'), columnas: {{ (int) $imp->columnas ?: 42 }}, ejemplo: @js(array_values($ejemplos)[0]['id'] ?? null),
            partes: @js($plantilla), inicial: @js($P::inicial()), repetibles: @js($P::REPETIBLES),
            nombres: @js(array_map(fn ($p) => $p[0], $P::PARTES)), html: '', cargando: false, _t: null,
            mover(i, paso) {
                const j = i + paso;
                if (j < 0 || j >= this.partes.length) return;
                [this.partes[i], this.partes[j]] = [this.partes[j], this.partes[i]];
                this.pedirVista();
            },
            agregar(tipo) {
                this.partes.push(Object.assign({ tipo, visible: true, alinear: tipo === 'texto' ? 'centro' : 'izq', negrita: false, tamano: 'normal' },
                    tipo === 'texto' ? { texto: '' } : { caracter: '-' }));
                this.pedirVista();
            },
            pedirVista() {
                clearTimeout(this._t);
                this._t = setTimeout(async () => {
                    this.cargando = true;
                    try {
                        const r = await fetch(@js(route('impresion.vista', $imp->Id)), {
                            method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                            body: JSON.stringify({ diseno: this.diseno, columnas: this.columnas, ejemplo: this.ejemplo, plantilla: this.partes }),
                        });
                        const d = await r.json();
                        this.html = r.ok ? d.html : '<p class="p-4 text-sm text-rose-600">' + (d.message || 'No se pudo armar la vista previa.') + '</p>';
                    } catch (e) {
                        this.html = '<p class="p-4 text-sm text-rose-600">Sin conexión con el servidor.</p>';
                    } finally {
                        this.cargando = false;
                    }
                }, 250);
            },
        };
    }
</script>
@endsection
