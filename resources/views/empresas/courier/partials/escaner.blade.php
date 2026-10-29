{{--
    Lector de guías: el lector de código de barras USB escribe la guía y da Enter; con la cámara del celular se lee el QR
    de la etiqueta (navegadores con BarcodeDetector: Chrome en Android). El componente Alpine que lo usa define leer(texto).
--}}
<div class="space-y-2">
    <form @submit.prevent="leer($refs.lector.value); $refs.lector.value = ''" class="flex gap-2">
        <input x-ref="lector" autofocus autocomplete="off" placeholder="Escanea la etiqueta o escribe el N° de guía…"
               class="flex-1 h-12 rounded-xl border-gray-300 font-mono font-bold uppercase tracking-widest placeholder:font-sans placeholder:tracking-normal placeholder:font-normal">
        <button type="button" x-show="'BarcodeDetector' in window" @click="camara ? cerrarCamara() : abrirCamara()"
                class="h-12 w-12 rounded-xl text-white" :class="camara ? 'bg-rose-600' : 'bg-gray-800'" title="Usar la cámara"><i class="fas" :class="camara ? 'fa-xmark' : 'fa-camera'"></i></button>
        <button class="h-12 px-4 rounded-xl bg-orange-600 text-white font-bold"><i class="fas fa-arrow-right"></i></button>
    </form>
    <video x-ref="video" x-show="camara" playsinline muted class="w-full max-h-72 rounded-xl bg-black object-cover"></video>
    <p x-show="aviso" x-text="aviso" class="rounded-xl px-3 py-2 text-sm font-bold" :class="avisoOk ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"></p>
</div>
