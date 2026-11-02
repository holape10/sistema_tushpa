{{-- Mover y cambiar el tamaño con botones (cómodo en el celular, donde arrastrar con el dedo cuesta más) --}}
<div class="rounded-xl bg-slate-50 p-2">
    <p class="text-[11px] font-bold text-gray-500 mb-1">Mover</p>
    <div class="grid grid-cols-3 gap-1 w-32 mx-auto">
        <span></span><button type="button" @click="mover(0, -10)" class="h-9 rounded-lg bg-white shadow-sm font-bold">↑</button><span></span>
        <button type="button" @click="mover(-10, 0)" class="h-9 rounded-lg bg-white shadow-sm font-bold">←</button>
        <button type="button" @click="mover(0, 10)" class="h-9 rounded-lg bg-white shadow-sm font-bold">↓</button>
        <button type="button" @click="mover(10, 0)" class="h-9 rounded-lg bg-white shadow-sm font-bold">→</button>
    </div>
    <div class="mt-2 flex gap-1">
        <button type="button" @click="crecer(-10)" class="flex-1 h-9 rounded-lg bg-white shadow-sm text-xs font-bold">➖ Más chico</button>
        <button type="button" @click="crecer(10)" class="flex-1 h-9 rounded-lg bg-white shadow-sm text-xs font-bold">➕ Más grande</button>
    </div>
</div>
