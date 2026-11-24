{{-- Imagen del producto (se ve en el PV táctil, el PV móvil y el listado) --}}
<section class="bg-white rounded-2xl shadow-sm p-5">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-bold text-gray-800">Imagen</h3>
        <button type="button" x-show="vistaImagen" x-cloak @click="borrarImagen()" class="text-xs font-semibold text-rose-600 hover:underline">Quitar</button>
    </div>
    <label class="group relative block aspect-square max-h-44 w-full mx-auto rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 overflow-hidden cursor-pointer hover:border-indigo-300">
        <img x-show="vistaImagen" :src="vistaImagen" alt="Imagen del producto" class="w-full h-full object-contain bg-white">
        <span x-show="!vistaImagen" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 gap-2 p-4 text-center">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span class="text-sm font-semibold text-indigo-600">Toca para subir una imagen</span>
            <span class="text-xs">o toma la foto desde el celular</span>
        </span>
        <span x-show="vistaImagen" x-cloak class="absolute bottom-2 inset-x-2 rounded-lg bg-black/60 text-white text-xs font-semibold py-1.5 text-center opacity-0 group-hover:opacity-100 transition">Cambiar imagen</span>
        <input x-ref="imagen" type="file" name="imagen" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="elegirImagen($event)">
    </label>
    <input type="hidden" name="quitar_imagen" :value="quitarImagen ? 1 : 0">
    {{-- Tomar la foto con la cámara del celular, la tablet o la webcam de la PC --}}
    <button type="button" @click="$dispatch('abrir-camara', { destino: 'imagen' })"
            class="mt-3 w-full h-10 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold inline-flex items-center justify-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Tomar foto con la cámara
    </button>
    <p class="text-xs text-gray-400 mt-2 text-center">JPG, PNG o WEBP hasta 8 MB (se achica sola para que cargue rápido). Esta es la foto principal.</p>

    {{-- Hasta 3 fotos más (4 en total): se ven en la tienda virtual al abrir el producto --}}
    <div class="mt-4">
        <p class="text-sm font-semibold text-gray-700 mb-2">Más fotos <span class="text-xs font-normal text-gray-400">(hasta {{ \App\Models\Producto::MAX_IMAGENES - 1 }}, para la tienda virtual)</span></p>
        <div class="grid grid-cols-3 gap-2">
            <template x-for="f in fotosExtra" :key="f.ruta">
                <div class="relative aspect-square rounded-xl overflow-hidden ring-1 ring-gray-200 bg-white">
                    <img :src="f.url" alt="" class="w-full h-full object-cover">
                    <button type="button" @click="quitarFotoExtra(f)" class="absolute top-1 right-1 w-6 h-6 rounded-full bg-black/60 text-white text-xs" title="Quitar">✕</button>
                </div>
            </template>
            <template x-for="(f, i) in fotosNuevas" :key="f">
                <div class="relative aspect-square rounded-xl overflow-hidden ring-2 ring-indigo-300 bg-white">
                    <img :src="f" alt="" class="w-full h-full object-cover">
                    <span class="absolute bottom-1 left-1 px-1.5 rounded bg-indigo-600 text-white text-[10px] font-bold">NUEVA</span>
                </div>
            </template>
            <label x-show="fotosExtra.length + fotosNuevas.length < {{ \App\Models\Producto::MAX_IMAGENES - 1 }}"
                   class="aspect-square rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 hover:border-indigo-300 cursor-pointer flex flex-col items-center justify-center text-gray-400 text-xs">
                <span class="text-2xl leading-none">+</span>Agregar
                <input x-ref="fotosExtra" type="file" name="fotos_extra[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" @change="elegirFotosExtra($event)">
            </label>
            <button type="button" x-show="fotosExtra.length + fotosNuevas.length < {{ \App\Models\Producto::MAX_IMAGENES - 1 }}" @click="$dispatch('abrir-camara', { destino: 'extra' })"
                    class="aspect-square rounded-xl border-2 border-dashed border-indigo-200 bg-indigo-50/50 hover:border-indigo-400 flex flex-col items-center justify-center text-indigo-500 text-xs font-semibold">
                <span class="text-2xl leading-none">📷</span>Cámara
            </button>
        </div>
        <template x-for="ruta in quitarExtra" :key="ruta"><input type="hidden" name="quitar_extra[]" :value="ruta"></template>
        @error('fotos_extra')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Cámara en vivo: la foto tomada entra al campo de la imagen como si se hubiera subido (se optimiza igual al guardar).
         Sin permiso o sin HTTPS, se abre la cámara del celular con el selector de archivos. --}}
    <div x-data="camaraProducto()" @abrir-camara.window="abrir($event.detail.destino)" @keydown.escape.window="cerrar()">
        <input x-ref="respaldo" type="file" accept="image/*" capture="environment" class="sr-only" @change="usarArchivo($event.target.files[0]); $event.target.value = ''">
        <div x-show="abierta" x-cloak class="fixed inset-0 z-[80] bg-black/80 flex items-center justify-center p-4" @click.self="cerrar()">
            <div class="w-full max-w-lg bg-white rounded-2xl overflow-hidden shadow-2xl">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                    <p class="font-bold text-gray-800">📷 Tomar foto</p>
                    <button type="button" @click="cerrar()" class="text-2xl leading-none text-gray-400 hover:text-gray-600" aria-label="Cerrar">×</button>
                </div>
                <div class="relative bg-black aspect-[4/3]">
                    <video x-ref="video" autoplay playsinline muted class="w-full h-full object-contain" x-show="!foto"></video>
                    <img x-show="foto" :src="foto" alt="" class="w-full h-full object-contain">
                    <p x-show="error" class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-white" x-text="error"></p>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-2 p-4">
                    <template x-if="!foto">
                        <div class="flex gap-2">
                            <button type="button" x-show="camaras > 1" @click="cambiarCamara()" class="h-11 px-4 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold">🔄 Cambiar cámara</button>
                            <button type="button" @click="capturar()" :disabled="!lista" class="h-11 px-6 rounded-xl bg-indigo-600 text-white text-sm font-bold disabled:opacity-50">● Capturar</button>
                        </div>
                    </template>
                    <template x-if="foto">
                        <div class="flex gap-2">
                            <button type="button" @click="otraVez()" class="h-11 px-4 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold">↺ Tomar otra</button>
                            <button type="button" @click="usar()" class="h-11 px-6 rounded-xl bg-emerald-600 text-white text-sm font-bold">✓ Usar esta foto</button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    function camaraProducto() {
        return {
            abierta: false, destino: 'imagen', stream: null, foto: null, blob: null, error: '', lista: false, camaras: 0, frontal: false,
            async abrir(destino) {
                this.destino = destino;
                // Sin HTTPS o sin cámaras en el navegador: la cámara del celular por el selector de archivos
                if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) { this.$refs.respaldo.click(); return; }
                this.abierta = true;
                this.foto = null;
                await this.encender();
            },
            async encender() {
                this.apagar();
                this.error = '';
                this.lista = false;
                try {
                    this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: this.frontal ? 'user' : 'environment', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false });
                    this.$refs.video.srcObject = this.stream;
                    this.lista = true;
                    const equipos = await navigator.mediaDevices.enumerateDevices();
                    this.camaras = equipos.filter(d => d.kind === 'videoinput').length;
                } catch (e) {
                    this.error = e.name === 'NotAllowedError' ? 'Permite el uso de la cámara en el navegador (el ícono del candado junto a la dirección) y vuelve a intentar.'
                        : 'No se encontró una cámara en este equipo. Puedes subir la foto desde tus archivos.';
                }
            },
            cambiarCamara() { this.frontal = !this.frontal; this.encender(); },
            capturar() {
                const v = this.$refs.video;
                if (!v.videoWidth) return;
                const c = document.createElement('canvas');
                c.width = v.videoWidth;
                c.height = v.videoHeight;
                c.getContext('2d').drawImage(v, 0, 0);
                c.toBlob(b => { this.blob = b; this.foto = URL.createObjectURL(b); }, 'image/jpeg', 0.9);
            },
            otraVez() { this.foto = null; this.blob = null; },
            usar() {
                if (!this.blob) return;
                this.usarArchivo(new File([this.blob], 'camara_' + Date.now() + '.jpg', { type: 'image/jpeg' }));
                this.cerrar();
            },
            // La foto entra al campo del formulario (la principal o una más) y se ve como si se hubiera elegido el archivo
            usarArchivo(archivo) {
                if (!archivo) return;
                const campo = document.querySelector(this.destino === 'extra' ? 'input[name="fotos_extra[]"]' : 'input[name="imagen"]');
                if (!campo) return;
                const dt = new DataTransfer();
                if (this.destino === 'extra') [...campo.files].forEach(f => dt.items.add(f));
                dt.items.add(archivo);
                campo.files = dt.files;
                campo.dispatchEvent(new Event('change', { bubbles: true }));
            },
            apagar() { this.stream?.getTracks().forEach(t => t.stop()); this.stream = null; },
            cerrar() { this.apagar(); this.abierta = false; this.foto = null; this.blob = null; },
        };
    }
</script>
