{{-- Una celda del plano (para la venta y para el editor). Variables en Alpine: celda, y en la venta estadoDe(celda) --}}
<template x-if="celda && (celda.t === 'A' || celda.t === 'H')">
    <span class="flex flex-col items-center justify-center leading-none">
        <span class="text-[13px] font-black" x-text="celda.n"></span>
        <span class="mt-0.5 text-[9px] font-bold opacity-70" x-text="(celda.t === 'H' ? 'HAM' : (celda.pos === 'V' ? 'VEN' : 'PAS'))"></span>
    </span>
</template>
<template x-if="celda && celda.t === 'CHOFER'"><i class="fas fa-user-tie text-slate-500"></i></template>
<template x-if="celda && celda.t === 'BANO'"><i class="fas fa-restroom text-slate-500"></i></template>
<template x-if="celda && celda.t === 'ESCALERA'"><i class="fas fa-stairs text-slate-500"></i></template>
<template x-if="celda && celda.t === 'PUERTA'"><i class="fas fa-door-open text-slate-500"></i></template>
