{{--
    Foto para redes: con el permiso del cliente, el mozo o promotor toma la foto con su celular, se le pone el marco con el
    logo y el nombre del local, y se comparte desde su celular (Facebook, Instagram, WhatsApp, TikTok...).
    La foto se arma en el celular y nunca se sube al sistema. Se abre con el evento "foto-redes" (botón de la cabecera).
--}}
<div x-data="fotoRedes(@js($fotoRedes))" @foto-redes.window="abrir()" @keydown.escape.window="cerrar()">
    <div x-show="visible" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center bg-slate-900/70 sm:items-center sm:p-4" @click.self="cerrar()">
        <div class="max-h-[92dvh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl sm:rounded-3xl">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-lg font-black text-gray-800">📸 Foto para redes</h3>
                <button type="button" @click="cerrar()" class="h-9 w-9 rounded-full bg-gray-100 text-xl font-bold text-gray-500">&times;</button>
            </div>

            {{-- 1. Permiso del cliente y cámara --}}
            <template x-if="!foto">
                <div class="space-y-4">
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border-2 px-4 py-3" :class="permiso ? 'border-emerald-400 bg-emerald-50' : 'border-amber-300 bg-amber-50'">
                        <input type="checkbox" x-model="permiso" class="mt-1 h-5 w-5 rounded text-emerald-600">
                        <span class="text-sm text-gray-700"><b>El cliente aceptó</b> que le tomemos la foto y la publiquemos en las redes del local.</span>
                    </label>
                    <button type="button" @click="$refs.camara.click()" :disabled="!permiso || armando"
                            class="flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 text-base font-black text-white disabled:bg-indigo-200"
                            x-text="armando ? 'Armando la foto…' : '📷 Abrir cámara'"></button>
                    <p class="text-center text-xs text-gray-400">La foto sale con el logo de <span x-text="local.nombre"></span> y no se guarda en el sistema.</p>
                </div>
            </template>
            <input type="file" accept="image/*" capture="environment" x-ref="camara" class="hidden" @change="armar($event)">

            {{-- 2. Vista previa y compartir --}}
            <template x-if="foto">
                <div class="space-y-3">
                    <img :src="foto" alt="Vista previa" class="w-full rounded-2xl shadow">
                    <button type="button" @click="compartir()" class="flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 text-base font-black text-white"
                            x-text="puedeCompartir ? '📤 Compartir en redes' : '⬇️ Descargar foto'"></button>
                    <button type="button" @click="otra()" class="h-11 w-full rounded-2xl bg-gray-100 text-sm font-bold text-gray-700">🔁 Tomar otra</button>
                    <p x-show="!puedeCompartir" class="text-center text-xs text-gray-500">Este navegador no comparte fotos directo: descárgala y súbela desde la app de la red social.</p>
                </div>
            </template>
            <p x-show="error" x-text="error" class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700"></p>
        </div>
    </div>
</div>

@include('empresas.comandas.partials.foto_redes_marco')
@push('scripts')
<script>
    function fotoRedes(local) {
        return {
            local, visible: false, permiso: false, armando: false, foto: null, archivo: null, error: '', puedeCompartir: false,
            abrir() { this.visible = true; this.permiso = false; this.otra(); },
            cerrar() { this.visible = false; this.otra(); },
            otra() {
                if (this.foto) URL.revokeObjectURL(this.foto);
                this.foto = null; this.archivo = null; this.error = '';
                if (this.$refs.camara) this.$refs.camara.value = '';
            },
            /** Foto + el marco que eligió la sucursal (Editar sucursal → Foto para redes) */
            async armar(e) {
                const original = e.target.files && e.target.files[0];
                if (!original) return;
                this.armando = true; this.error = '';
                try {
                    const url = URL.createObjectURL(original);
                    const img = await cargarImagenRedes(url);
                    URL.revokeObjectURL(url);
                    let logo = null;
                    if (this.local.logo) {
                        try { logo = await cargarImagenRedes(this.local.logo); } catch (ignorado) { /* sin logo: solo el nombre */ }
                    }
                    const c = marcoFotoRedes(img, logo, this.local);
                    const blob = await new Promise(ok => c.toBlob(ok, 'image/jpeg', 0.9));
                    const nombre = (this.local.nombre || 'foto').normalize('NFD').replace(/[^A-Za-z0-9]+/g, '-').replace(/^-|-$/g, '').toLowerCase();
                    this.archivo = new File([blob], `${nombre}-${Date.now()}.jpg`, { type: 'image/jpeg' });
                    this.puedeCompartir = !!(navigator.canShare && navigator.canShare({ files: [this.archivo] }));
                    this.foto = URL.createObjectURL(blob);
                } catch (err) {
                    this.error = 'No se pudo armar la foto. Intenta de nuevo.';
                } finally {
                    this.armando = false;
                }
            },
            async compartir() {
                if (!this.archivo) return;
                if (this.puedeCompartir) {
                    try {
                        await navigator.share({ files: [this.archivo], title: this.local.nombre, text: '📸 ' + this.local.nombre });
                    } catch (err) {
                        if (err.name !== 'AbortError') this.error = 'No se pudo compartir. Descárgala y súbela desde la app.';
                    }
                    return;
                }
                const a = document.createElement('a');
                a.href = this.foto; a.download = this.archivo.name;
                document.body.appendChild(a); a.click(); a.remove();
            },
        };
    }
</script>
@endpush
