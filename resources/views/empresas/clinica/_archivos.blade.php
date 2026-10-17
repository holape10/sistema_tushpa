{{--
    Radiografías, fotos y documentos del paciente: se suben desde la PC o con la cámara del celular/tablet.
    Las fotos grandes se achican en el navegador (máx. 2400 px) para que suban rápido sin perder detalle.
--}}
@php
    $bytes = function (string $v): int {
        $n = (float) $v;
        return (int) match (strtolower(substr(trim($v), -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    };
    $limiteSubida = min(20 * 1048576, $bytes((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX, $bytes((string) ini_get('post_max_size')) ?: PHP_INT_MAX);
@endphp
@once
<script>
    window.archivosClinica = function (hisId, limite) {
        const CSRF = '{{ csrf_token() }}';
        // Achica fotos grandes (las radiografías siguen nítidas a 2400 px)
        const achicar = (f) => new Promise((ok) => {
            if (!/^image\/(jpeg|png|webp|bmp)$/.test(f.type) || f.size < 1.5 * 1048576) return ok(f);
            const img = new Image();
            img.onload = () => {
                const max = 2400, k = Math.min(1, max / Math.max(img.width, img.height));
                const c = document.createElement('canvas'); c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
                c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                c.toBlob(b => ok(b ? new File([b], f.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : f), 'image/jpeg', 0.9);
                URL.revokeObjectURL(img.src);
            };
            img.onerror = () => ok(f);
            img.src = URL.createObjectURL(f);
        });
        return {
            lista: [], filtro: '', tipo: 'RADIOGRAFIA', descripcion: '', subiendo: false, progreso: 0, visor: null,
            TIPOS: @js(\App\Http\Controllers\ClinicaArchivoController::TIPOS),
            get visibles() { return this.lista.filter(a => !this.filtro || a.tipo === this.filtro); },
            fecha(f) { return f ? new Date(String(f).replace(' ', 'T')).toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' }) : ''; },
            async cargar() {
                const r = await fetch('{{ url('clinica/historia') }}/' + hisId + '/archivos', { headers: { Accept: 'application/json' } });
                this.lista = r.ok ? await r.json() : [];
            },
            async subir(ev) {
                const elegidos = [...ev.target.files]; ev.target.value = '';
                if (!elegidos.length) return;
                this.subiendo = true; this.progreso = 0;
                const fd = new FormData();
                for (const f of elegidos) {
                    const g = await achicar(f);
                    if (g.size > limite) { tushpaAviso('"' + f.name + '" pesa más de ' + Math.floor(limite / 1048576) + ' MB.', 'error'); this.subiendo = false; return; }
                    fd.append('archivos[]', g, g.name);
                }
                fd.append('tipo', this.tipo); fd.append('descripcion', this.descripcion);
                const x = new XMLHttpRequest();
                x.open('POST', '{{ url('clinica/historia') }}/' + hisId + '/archivos');
                x.setRequestHeader('X-CSRF-TOKEN', CSRF); x.setRequestHeader('Accept', 'application/json');
                x.upload.onprogress = e => { if (e.lengthComputable) this.progreso = Math.round(e.loaded / e.total * 100); };
                x.onload = () => {
                    this.subiendo = false;
                    let j = {}; try { j = JSON.parse(x.responseText); } catch (e) {}
                    const ok = x.status < 300 && j.ok;
                    tushpaAviso(j.mensaje || Object.values(j.errors || {}).flat()[0] || (x.status === 413 ? 'El archivo es muy pesado para el servidor.' : 'No se pudo subir.'), ok ? 'ok' : 'error');
                    if (ok) { this.descripcion = ''; this.cargar(); }
                };
                x.onerror = () => { this.subiendo = false; tushpaAviso('Sin conexión: no se pudo subir.', 'error'); };
                x.send(fd);
            },
            async guardarVisor() {
                const a = this.visor;
                const r = await fetch('{{ url('clinica/archivos') }}/' + a.arc_id, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify({ tipo: a.tipo, descripcion: a.descripcion }) });
                const j = await r.json().catch(() => ({})); tushpaAviso(j.mensaje || 'Error', j.ok ? 'ok' : 'error');
            },
            async borrar(a) {
                if (!confirm('¿Borrar "' + (a.descripcion || a.nombre) + '"? No se puede recuperar.')) return;
                const r = await fetch('{{ url('clinica/archivos') }}/' + a.arc_id, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } });
                const j = await r.json().catch(() => ({})); tushpaAviso(j.mensaje || 'No se pudo borrar.', j.ok ? 'ok' : 'error');
                if (j.ok) { this.visor = null; this.cargar(); }
            },
            mover(paso) { const v = this.visibles, i = v.findIndex(a => a.arc_id === this.visor.arc_id); this.visor = v[(i + paso + v.length) % v.length]; },
        };
    };
</script>
@endonce

<div x-data="archivosClinica({{ $h->his_cli_id }}, {{ $limiteSubida }})" x-init="cargar()" class="bg-white rounded-2xl shadow-sm p-4 space-y-3">
    <div class="flex flex-wrap items-center gap-2">
        <h2 class="font-bold text-gray-700 flex-1"><i class="fas fa-x-ray text-teal-600"></i> Radiografías y fotos <span class="text-xs font-normal text-gray-400" x-text="'(' + lista.length + ')'"></span></h2>
        <select x-model="filtro" class="rounded-lg border-gray-300 text-xs py-1">
            <option value="">Todas</option><template x-for="(n, k) in TIPOS" :key="k"><option :value="k" x-text="n"></option></template>
        </select>
    </div>
    <div class="flex flex-wrap items-end gap-2 rounded-xl bg-gray-50 p-3">
        <label class="text-xs font-semibold text-gray-500">Tipo
            <select x-model="tipo" class="block mt-1 rounded-lg border-gray-300 text-sm"><template x-for="(n, k) in TIPOS" :key="k"><option :value="k" x-text="n"></option></template></select></label>
        <label class="text-xs font-semibold text-gray-500 flex-1 min-w-[160px]">Descripción (opcional)
            <input x-model="descripcion" maxlength="200" placeholder="Ej. Periapical pieza 36 · Antes del tratamiento" class="block w-full mt-1 rounded-lg border-gray-300 text-sm"></label>
        <label class="px-3 py-2 rounded-lg bg-teal-600 text-white text-sm font-bold cursor-pointer" :class="subiendo && 'opacity-50 pointer-events-none'">
            <i class="fas fa-upload"></i> Subir archivos
            <input type="file" multiple accept="image/*,application/pdf" class="hidden" @change="subir($event)"></label>
        <label class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm font-bold cursor-pointer" :class="subiendo && 'opacity-50 pointer-events-none'">
            <i class="fas fa-camera"></i> Tomar foto
            <input type="file" accept="image/*" capture="environment" class="hidden" @change="subir($event)"></label>
        <div x-show="subiendo" class="w-full h-2 rounded-full bg-gray-200"><div class="h-2 rounded-full bg-teal-500" :style="'width:' + progreso + '%'"></div></div>
    </div>
    <p x-show="!visibles.length" class="text-sm text-gray-400 text-center py-3">Aún no hay archivos.</p>
    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
        <template x-for="a in visibles" :key="a.arc_id">
            <button type="button" @click="a.imagen ? visor = a : window.open(a.url, '_blank')" class="group relative aspect-square rounded-xl overflow-hidden bg-gray-100 border text-left">
                <img x-show="a.imagen" :src="a.url" loading="lazy" class="w-full h-full object-cover" alt="">
                <div x-show="!a.imagen" class="w-full h-full flex flex-col items-center justify-center text-rose-600"><i class="fas fa-file-pdf text-3xl"></i><span class="text-[10px] px-1 truncate max-w-full" x-text="a.nombre"></span></div>
                <span class="absolute bottom-0 inset-x-0 bg-black/55 text-white text-[10px] px-1.5 py-0.5 truncate" x-text="TIPOS[a.tipo] + ' · ' + fecha(a.creado)"></span>
            </button>
        </template>
    </div>

    {{-- Visor de imágenes --}}
    <div x-show="visor" x-cloak class="fixed inset-0 z-[70] bg-black/90 flex flex-col" @keydown.escape.window="visor = null" @keydown.arrow-right.window="visor && mover(1)" @keydown.arrow-left.window="visor && mover(-1)">
        <template x-if="visor">
            <div class="flex-1 flex flex-col min-h-0">
                <div class="flex items-center gap-2 p-3 text-white">
                    <span class="flex-1 text-sm truncate" x-text="visor.nombre + ' · ' + fecha(visor.creado)"></span>
                    <a :href="visor.url" target="_blank" class="px-3 py-1.5 rounded-lg bg-white/15 text-sm">Abrir original</a>
                    <button type="button" @click="borrar(visor)" class="px-3 py-1.5 rounded-lg bg-rose-600 text-sm">Borrar</button>
                    <button type="button" @click="visor = null" class="px-3 py-1.5 rounded-lg bg-white/15 text-sm">✕</button>
                </div>
                <div class="flex-1 min-h-0 flex items-center justify-center relative px-2">
                    <button type="button" @click="mover(-1)" class="absolute left-2 w-10 h-10 rounded-full bg-white/20 text-white text-xl">‹</button>
                    <img :src="visor.url" class="max-w-full max-h-full object-contain" alt="">
                    <button type="button" @click="mover(1)" class="absolute right-2 w-10 h-10 rounded-full bg-white/20 text-white text-xl">›</button>
                </div>
                <div class="p-3 flex flex-wrap gap-2 items-center">
                    <select x-model="visor.tipo" class="rounded-lg text-sm"><template x-for="(n, k) in TIPOS" :key="k"><option :value="k" x-text="n"></option></template></select>
                    <input x-model="visor.descripcion" maxlength="200" placeholder="Descripción" class="flex-1 min-w-[160px] rounded-lg text-sm">
                    <button type="button" @click="guardarVisor()" class="px-3 py-2 rounded-lg bg-teal-600 text-white text-sm font-bold">Guardar</button>
                </div>
            </div>
        </template>
    </div>
</div>
