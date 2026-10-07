{{--
    Odontograma por caras (norma peruana: rojo = mal estado / por tratar, azul = tratamiento en buen estado).
    Usa del componente padre: piezas = {"36": {"e": "CORONA", "c": {"O": "CARIES"}}}, editable (bool), pincel, denticion ('ADULTO' | 'NINO').
--}}
@once
<script>
    window.Odonto = {
        adulto: [[18, 17, 16, 15, 14, 13, 12, 11], [21, 22, 23, 24, 25, 26, 27, 28], [48, 47, 46, 45, 44, 43, 42, 41], [31, 32, 33, 34, 35, 36, 37, 38]],
        nino: [[55, 54, 53, 52, 51], [61, 62, 63, 64, 65], [85, 84, 83, 82, 81], [71, 72, 73, 74, 75]],
        rojo: '#dc2626', azul: '#2563eb',
        // Hallazgos que se marcan en una cara del diente
        caras: { 'CARIES': 'rojo', 'RESTAURACION': 'azul', 'RESTAURACION DEFECTUOSA': 'rojo', 'SELLANTE': 'azul', 'FRACTURA': 'rojo' },
        // Hallazgos de la pieza completa
        piezas: { 'POR EXTRAER': 'rojo', 'AUSENTE': 'azul', 'CORONA': 'azul', 'CORONA DEFECTUOSA': 'rojo', 'ENDODONCIA': 'azul',
                  'IMPLANTE': 'azul', 'REMANENTE RADICULAR': 'rojo', 'PROTESIS FIJA': 'azul', 'MOVILIDAD': 'rojo', 'EXTRUSION': 'rojo' },
        nombresCara: { V: 'Vestibular', L: 'Lingual / palatino', M: 'Mesial', D: 'Distal', O: 'Oclusal / incisal' },
        superior(n) { const q = Math.floor(n / 10); return [1, 2, 5, 6].includes(q); },
        derechaPaciente(n) { const q = Math.floor(n / 10); return [1, 4, 5, 8].includes(q); },
        // Qué cara es cada zona del dibujo según la pieza (arriba / abajo / izquierda / derecha / centro)
        cara(n, zona) {
            if (zona === 'centro') return 'O';
            if (zona === 'arriba') return this.superior(n) ? 'V' : 'L';
            if (zona === 'abajo') return this.superior(n) ? 'L' : 'V';
            const haciaLineaMedia = this.derechaPaciente(n) ? 'der' : 'izq';
            return zona === haciaLineaMedia ? 'M' : 'D';
        },
        colorDe(h) { const t = this.caras[h] || this.piezas[h]; return t ? this[t] : null; },
        // Normaliza datos antiguos ("36": "CARIES") al formato por caras
        pieza(p) { return !p ? { e: null, c: {} } : typeof p === 'string' ? { e: this.piezas[p] ? p : null, c: this.caras[p] ? { O: p } : {} } : { e: p.e || null, c: p.c || {} }; },
        aplicar(todas, n, zona, pincel) {
            const res = { ...todas }, p = this.pieza(res[n]);
            if (pincel === 'SANO') { delete res[n]; return res; }
            if (this.caras[pincel]) { const c = this.cara(n, zona); p.c = { ...p.c }; p.c[c] === pincel ? delete p.c[c] : p.c[c] = pincel; }
            else if (this.piezas[pincel]) { p.e = p.e === pincel ? null : pincel; }
            if (!p.e && !Object.keys(p.c).length) delete res[n]; else res[n] = p;
            return res;
        },
        resumen(todas) {
            return Object.keys(todas).sort().map(n => {
                const p = this.pieza(todas[n]), partes = [];
                if (p.e) partes.push(p.e);
                for (const [c, h] of Object.entries(p.c)) partes.push(h + ' (' + this.nombresCara[c].split(' ')[0].toLowerCase() + ')');
                return n + ': ' + partes.join(', ');
            }).filter(x => !x.endsWith(': '));
        },
    };
</script>
@endonce

<div class="space-y-3">
    <div class="flex flex-wrap items-center gap-2">
        <div class="inline-flex rounded-lg border overflow-hidden text-xs font-bold">
            <button type="button" @click="denticion = 'ADULTO'" class="px-3 py-1" :class="denticion === 'ADULTO' ? 'bg-teal-600 text-white' : 'bg-white'">Adulto</button>
            <button type="button" @click="denticion = 'NINO'" class="px-3 py-1" :class="denticion === 'NINO' ? 'bg-teal-600 text-white' : 'bg-white'">Niño</button>
        </div>
        <span class="text-xs text-gray-500"><span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:#dc2626"></span> por tratar ·
            <span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:#2563eb"></span> tratamiento en buen estado</span>
    </div>

    <template x-if="editable">
        <div class="space-y-1.5">
            <div class="flex flex-wrap gap-1 items-center"><span class="text-[11px] font-bold text-gray-500 w-24">Clic en una cara:</span>
                <template x-for="(t, h) in Odonto.caras" :key="h">
                    <button type="button" @click="pincel = h" class="px-2 py-0.5 rounded-md text-[11px] font-semibold border"
                            :class="pincel === h ? 'ring-2 ring-teal-500 border-teal-500' : 'border-gray-300'" :style="'color:' + Odonto[t]" x-text="h"></button>
                </template>
            </div>
            <div class="flex flex-wrap gap-1 items-center"><span class="text-[11px] font-bold text-gray-500 w-24">Pieza completa:</span>
                <template x-for="(t, h) in Odonto.piezas" :key="h">
                    <button type="button" @click="pincel = h" class="px-2 py-0.5 rounded-md text-[11px] font-semibold border"
                            :class="pincel === h ? 'ring-2 ring-teal-500 border-teal-500' : 'border-gray-300'" :style="'color:' + Odonto[t]" x-text="h"></button>
                </template>
                <button type="button" @click="pincel = 'SANO'" class="px-2 py-0.5 rounded-md text-[11px] font-semibold border"
                        :class="pincel === 'SANO' ? 'ring-2 ring-teal-500 border-teal-500' : 'border-gray-300'">Borrar pieza</button>
            </div>
        </div>
    </template>

    <div class="rounded-xl border border-gray-200 p-3 bg-white overflow-x-auto">
        <template x-for="(arcada, ai) in [ (denticion === 'NINO' ? Odonto.nino : Odonto.adulto).slice(0, 2), (denticion === 'NINO' ? Odonto.nino : Odonto.adulto).slice(2, 4) ]" :key="ai">
            <div class="flex justify-center gap-4 min-w-max" :class="ai === 1 ? 'mt-2 pt-2 border-t border-dashed border-gray-300' : ''">
                <template x-for="(lado, li) in arcada" :key="li">
                    <div class="flex gap-0.5">
                        <template x-for="n in lado" :key="n">
                            <div class="flex flex-col items-center" :class="ai === 1 ? 'flex-col-reverse' : ''" :title="'Pieza ' + n">
                                <span class="text-[10px] font-bold text-gray-600" x-text="n"></span>
                                <svg viewBox="0 0 40 40" class="w-9 h-9" :class="editable ? 'cursor-pointer' : ''">
                                    <g stroke="#6b7280" stroke-width="1">
                                        <polygon points="2,2 38,2 28,12 12,12" :fill="Odonto.colorDe(Odonto.pieza(piezas[n]).c[Odonto.cara(n, 'arriba')]) || '#fff'" @click="editable && pincel && (piezas = Odonto.aplicar(piezas, n, 'arriba', pincel))"></polygon>
                                        <polygon points="12,28 28,28 38,38 2,38" :fill="Odonto.colorDe(Odonto.pieza(piezas[n]).c[Odonto.cara(n, 'abajo')]) || '#fff'" @click="editable && pincel && (piezas = Odonto.aplicar(piezas, n, 'abajo', pincel))"></polygon>
                                        <polygon points="2,2 12,12 12,28 2,38" :fill="Odonto.colorDe(Odonto.pieza(piezas[n]).c[Odonto.cara(n, 'izq')]) || '#fff'" @click="editable && pincel && (piezas = Odonto.aplicar(piezas, n, 'izq', pincel))"></polygon>
                                        <polygon points="38,2 38,38 28,28 28,12" :fill="Odonto.colorDe(Odonto.pieza(piezas[n]).c[Odonto.cara(n, 'der')]) || '#fff'" @click="editable && pincel && (piezas = Odonto.aplicar(piezas, n, 'der', pincel))"></polygon>
                                        <rect x="12" y="12" width="16" height="16" :fill="Odonto.colorDe(Odonto.pieza(piezas[n]).c.O) || '#fff'" @click="editable && pincel && (piezas = Odonto.aplicar(piezas, n, 'centro', pincel))"></rect>
                                    </g>
                                    {{-- Hallazgo de la pieza completa --}}
                                    <g pointer-events="none" :stroke="Odonto.colorDe(Odonto.pieza(piezas[n]).e)" :fill="Odonto.colorDe(Odonto.pieza(piezas[n]).e)" stroke-width="3">
                                        <path x-show="['POR EXTRAER', 'AUSENTE'].includes(Odonto.pieza(piezas[n]).e)" d="M4 4L36 36M36 4L4 36"></path>
                                        <circle x-show="['CORONA', 'CORONA DEFECTUOSA'].includes(Odonto.pieza(piezas[n]).e)" cx="20" cy="20" r="17" fill="none"></circle>
                                        <path x-show="Odonto.pieza(piezas[n]).e === 'ENDODONCIA'" d="M20 2V38"></path>
                                        <path x-show="Odonto.pieza(piezas[n]).e === 'PROTESIS FIJA'" d="M0 6H40"></path>
                                        <text x-show="['IMPLANTE', 'REMANENTE RADICULAR', 'MOVILIDAD', 'EXTRUSION'].includes(Odonto.pieza(piezas[n]).e)" x="20" y="25" text-anchor="middle" font-size="13" font-weight="bold" stroke="none"
                                              x-text="({ 'IMPLANTE': 'IMP', 'REMANENTE RADICULAR': 'RR', 'MOVILIDAD': 'M', 'EXTRUSION': 'EX' })[Odonto.pieza(piezas[n]).e] || ''"></text>
                                    </g>
                                </svg>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>
    <ul class="text-xs text-gray-600 columns-1 sm:columns-2" x-show="Odonto.resumen(piezas).length">
        <template x-for="l in Odonto.resumen(piezas)" :key="l"><li x-text="l"></li></template>
    </ul>
</div>
