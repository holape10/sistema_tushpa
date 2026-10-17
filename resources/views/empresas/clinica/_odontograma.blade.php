{{--
    Odontograma por caras (norma peruana: rojo = mal estado / por tratar, azul = tratamiento en buen estado).
    Usa del componente padre: piezas = {"14": {"e": ["ENDODONCIA", "PROTESIS FIJA"], "c": {"O": "CARIES"}}}, editable (bool), pincel,
    denticion ('ADULTO' | 'NINO'). Una pieza puede tener varios hallazgos de pieza completa y uno por cara.
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
                  'IMPLANTE': 'azul', 'REMANENTE RADICULAR': 'rojo', 'PROTESIS FIJA': 'azul', 'PROTESIS FIJA DEFECTUOSA': 'rojo',
                  'PROTESIS REMOVIBLE': 'azul', 'PROTESIS REMOVIBLE DEFECTUOSA': 'rojo', 'MOVILIDAD': 'rojo',
                  // Norma técnica del odontograma (MINSA): posición y desarrollo de la pieza
                  'EXTRUSION': 'azul', 'INTRUSION': 'azul', 'EN ERUPCION': 'azul', 'GIROVERSION': 'azul', 'DIASTEMA': 'azul',
                  'SUPERNUMERARIA': 'azul', 'ECTOPICA': 'azul', 'CLAVIJA': 'azul', 'EDENTULO TOTAL': 'azul',
                  // Defectos del esmalte y lesiones iniciales (se anotan con su sigla)
                  'FOSAS Y FISURAS PROFUNDAS': 'rojo', 'MANCHA BLANCA': 'rojo', 'HIPOMINERALIZACION': 'rojo', 'HIPOPLASIA': 'rojo', 'OPACIDAD DEL ESMALTE': 'rojo' },
        // Grupos para que los botones no sean una lista larga
        grupos: { 'Tratamientos': ['CORONA', 'CORONA DEFECTUOSA', 'ENDODONCIA', 'IMPLANTE', 'PROTESIS FIJA', 'PROTESIS FIJA DEFECTUOSA', 'PROTESIS REMOVIBLE', 'PROTESIS REMOVIBLE DEFECTUOSA'],
                  'Pieza': ['POR EXTRAER', 'AUSENTE', 'REMANENTE RADICULAR', 'MOVILIDAD', 'EDENTULO TOTAL'],
                  'Posición y desarrollo': ['EXTRUSION', 'INTRUSION', 'EN ERUPCION', 'GIROVERSION', 'DIASTEMA', 'SUPERNUMERARIA', 'ECTOPICA', 'CLAVIJA'],
                  'Esmalte': ['FOSAS Y FISURAS PROFUNDAS', 'MANCHA BLANCA', 'HIPOMINERALIZACION', 'HIPOPLASIA', 'OPACIDAD DEL ESMALTE'] },
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
        // Normaliza datos antiguos ("36": "CARIES" o "e": "CORONA") al formato con varios hallazgos por pieza
        pieza(p) {
            if (!p) return { e: [], c: {} };
            if (typeof p === 'string') return { e: this.piezas[p] ? [p] : [], c: this.caras[p] ? { O: p } : {} };
            return { e: Array.isArray(p.e) ? p.e : (p.e ? [p.e] : []), c: p.c || {} };
        },
        tiene(p, ...hallazgos) { const e = this.pieza(p).e; return hallazgos.some(h => e.includes(h)); },
        // Hallazgos que se excluyen entre sí (no puede estar ausente y por extraer, ni con corona buena y defectuosa)
        opuestos: { 'POR EXTRAER': 'AUSENTE', 'AUSENTE': 'POR EXTRAER', 'CORONA': 'CORONA DEFECTUOSA', 'CORONA DEFECTUOSA': 'CORONA',
                    'PROTESIS FIJA': 'PROTESIS FIJA DEFECTUOSA', 'PROTESIS FIJA DEFECTUOSA': 'PROTESIS FIJA',
                    'PROTESIS REMOVIBLE': 'PROTESIS REMOVIBLE DEFECTUOSA', 'PROTESIS REMOVIBLE DEFECTUOSA': 'PROTESIS REMOVIBLE',
                    'EXTRUSION': 'INTRUSION', 'INTRUSION': 'EXTRUSION' },
        // Letras de los hallazgos que se dibujan como texto (IMP, RR, M, EX), juntas si hay varios
        letras(p) {
            const abrev = { 'IMPLANTE': 'IMP', 'REMANENTE RADICULAR': 'RR', 'MOVILIDAD': 'M', 'SUPERNUMERARIA': 'S', 'ECTOPICA': 'E',
                            'FOSAS Y FISURAS PROFUNDAS': 'FFP', 'MANCHA BLANCA': 'MB', 'HIPOMINERALIZACION': 'HM', 'HIPOPLASIA': 'HP', 'OPACIDAD DEL ESMALTE': 'O' };
            const hs = this.pieza(p).e.filter(h => abrev[h]);
            return { texto: hs.map(h => abrev[h]).join(' '), color: hs.some(h => this.piezas[h] === 'rojo') ? this.rojo : this.azul, tam: hs.length > 1 ? 9 : 13 };
        },
        aplicar(todas, n, zona, pincel) {
            const res = { ...todas }, p = this.pieza(res[n]);
            if (pincel === 'SANO') { delete res[n]; return res; }
            if (this.caras[pincel]) { const c = this.cara(n, zona); p.c = { ...p.c }; p.c[c] === pincel ? delete p.c[c] : p.c[c] = pincel; }
            else if (this.piezas[pincel]) {
                // Tocar el diente agrega el hallazgo (endodoncia + prótesis fija + …); tocar otra vez lo quita
                p.e = p.e.includes(pincel) ? p.e.filter(h => h !== pincel) : [...p.e.filter(h => h !== this.opuestos[pincel]), pincel];
            }
            if (!p.e.length && !Object.keys(p.c).length) delete res[n]; else res[n] = { e: p.e, c: p.c };
            return res;
        },
        resumen(todas) {
            return Object.keys(todas).sort().map(n => {
                const p = this.pieza(todas[n]), partes = [...p.e];
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
            {{-- Hallazgos de pieza completa por grupo (se pueden marcar varios en el mismo diente) --}}
            <template x-for="(lista, grupo) in Odonto.grupos" :key="grupo">
                <div class="flex flex-wrap gap-1 items-center"><span class="text-[11px] font-bold text-gray-500 w-24" x-text="grupo + ':'"></span>
                    <template x-for="h in lista" :key="h">
                        <button type="button" @click="pincel = h" class="px-2 py-0.5 rounded-md text-[11px] font-semibold border"
                                :class="pincel === h ? 'ring-2 ring-teal-500 border-teal-500' : 'border-gray-300'" :style="'color:' + Odonto[Odonto.piezas[h]]" x-text="h"></button>
                    </template>
                </div>
            </template>
            <div class="flex flex-wrap gap-1 items-center"><span class="w-24"></span>
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
                                    {{-- Hallazgos de la pieza completa (pueden ser varios a la vez, cada uno con su dibujo y su color) --}}
                                    <g pointer-events="none" stroke-width="3" fill="none">
                                        <path x-show="Odonto.tiene(piezas[n], 'POR EXTRAER', 'AUSENTE')" d="M4 4L36 36M36 4L4 36"
                                              :stroke="Odonto.tiene(piezas[n], 'POR EXTRAER') ? Odonto.rojo : Odonto.azul"></path>
                                        <circle x-show="Odonto.tiene(piezas[n], 'CORONA', 'CORONA DEFECTUOSA')" cx="20" cy="20" r="17"
                                                :stroke="Odonto.tiene(piezas[n], 'CORONA DEFECTUOSA') ? Odonto.rojo : Odonto.azul"></circle>
                                        <path x-show="Odonto.tiene(piezas[n], 'ENDODONCIA')" d="M20 2V38" :stroke="Odonto.azul"></path>
                                        <path x-show="Odonto.tiene(piezas[n], 'PROTESIS FIJA', 'PROTESIS FIJA DEFECTUOSA')" d="M0 6H40"
                                              :stroke="Odonto.tiene(piezas[n], 'PROTESIS FIJA DEFECTUOSA') ? Odonto.rojo : Odonto.azul"></path>
                                        {{-- Prótesis removible: dos líneas paralelas --}}
                                        <path x-show="Odonto.tiene(piezas[n], 'PROTESIS REMOVIBLE', 'PROTESIS REMOVIBLE DEFECTUOSA')" d="M0 3H40M0 9H40" stroke-width="2"
                                              :stroke="Odonto.tiene(piezas[n], 'PROTESIS REMOVIBLE DEFECTUOSA') ? Odonto.rojo : Odonto.azul"></path>
                                        {{-- Edéntulo total: línea horizontal --}}
                                        <path x-show="Odonto.tiene(piezas[n], 'EDENTULO TOTAL')" d="M0 34H40" :stroke="Odonto.azul"></path>
                                        {{-- En erupción: línea en zigzag --}}
                                        <path x-show="Odonto.tiene(piezas[n], 'EN ERUPCION')" d="M20 2L26 9L14 16L26 23L14 30L20 38" stroke-width="2" :stroke="Odonto.azul"></path>
                                        {{-- Extruida (flecha hacia el plano oclusal) e intruida (flecha hacia la raíz) --}}
                                        <path x-show="Odonto.tiene(piezas[n], 'EXTRUSION')" :d="Odonto.superior(n) ? 'M20 6V34M13 27L20 35L27 27' : 'M20 34V6M13 13L20 5L27 13'" stroke-width="2.5" :stroke="Odonto.azul"></path>
                                        <path x-show="Odonto.tiene(piezas[n], 'INTRUSION')" :d="Odonto.superior(n) ? 'M20 34V6M13 13L20 5L27 13' : 'M20 6V34M13 27L20 35L27 27'" stroke-width="2.5" :stroke="Odonto.azul"></path>
                                        {{-- Giroversión: flecha curva --}}
                                        <path x-show="Odonto.tiene(piezas[n], 'GIROVERSION')" d="M6 30Q20 42 34 30M29 26L34 30L28 34" stroke-width="2" :stroke="Odonto.azul"></path>
                                        {{-- Diastema: paréntesis invertidos al costado --}}
                                        <path x-show="Odonto.tiene(piezas[n], 'DIASTEMA')" d="M33 6Q39 20 33 34M39 6Q33 20 39 34" stroke-width="2" :stroke="Odonto.azul"></path>
                                        {{-- Pieza en clavija: triángulo --}}
                                        <path x-show="Odonto.tiene(piezas[n], 'CLAVIJA')" d="M20 4L37 36H3Z" stroke-width="2" :stroke="Odonto.azul"></path>
                                        <circle x-show="Odonto.tiene(piezas[n], 'SUPERNUMERARIA')" cx="20" cy="20" r="11" stroke-width="2" :stroke="Odonto.azul"></circle>
                                        <text x-show="Odonto.letras(piezas[n]).texto" x="20" y="25" text-anchor="middle" font-weight="bold" stroke="none"
                                              :font-size="Odonto.letras(piezas[n]).tam" :fill="Odonto.letras(piezas[n]).color" x-text="Odonto.letras(piezas[n]).texto"></text>
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
