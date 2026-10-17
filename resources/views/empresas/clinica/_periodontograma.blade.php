{{--
    Periodontograma de la atención (dientes permanentes). Va dentro de x-data="atencion()", que mezcla perioMixin().
    Por diente: ausente (tocar el número), movilidad y furca (0-3); por cara vestibular y palatina/lingual, 3 sitios
    (en el orden en que se ven): sangrado, placa, margen gingival (MG) y profundidad de sondaje (PS). NIC = PS + MG.
--}}
@php
    $arcadas = ['Superior' => [[18, 17, 16, 15, 14, 13, 12, 11], [21, 22, 23, 24, 25, 26, 27, 28], 'Palatino'],
                'Inferior' => [[48, 47, 46, 45, 44, 43, 42, 41], [31, 32, 33, 34, 35, 36, 37, 38], 'Lingual']];
    $celda = 'w-[22px] h-[22px] p-0 text-center text-[11px] border border-gray-300 rounded focus:ring-1 focus:ring-teal-500';
@endphp
@once
<script>
    window.perioMixin = function (datos) {
        const PIEZAS = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28, 48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
        const lado = (x) => ({ ps: [0, 1, 2].map(i => x?.ps?.[i] ?? ''), mg: [0, 1, 2].map(i => x?.mg?.[i] ?? ''), s: [0, 1, 2].map(i => !!x?.s?.[i]), p: [0, 1, 2].map(i => !!x?.p?.[i]) });
        const perio = {};
        PIEZAS.forEach(n => { const d = (datos || {})[n] || {}; perio[n] = { aus: !!d.aus, mov: d.mov ?? '', fur: d.fur ?? '', v: lado(d.v), l: lado(d.l) }; });
        return {
            perio,
            perioCls(v) { const n = Number(v); return v === '' || v === null ? '' : n >= 6 ? 'bg-rose-500 text-white border-rose-500' : n >= 4 ? 'bg-amber-300 border-amber-400' : 'bg-white'; },
            perioNic(c, i) { const ps = c.ps[i]; return ps === '' || ps === null ? '' : Number(ps) + (Number(c.mg[i]) || 0); },
            // Solo los dientes con algo anotado (lo demás no se guarda)
            perioDatos() {
                const out = {};
                Object.entries(this.perio).forEach(([n, d]) => {
                    const usado = c => c.ps.some(v => v !== '') || c.mg.some(v => v !== '') || c.s.includes(true) || c.p.includes(true);
                    if (d.aus || d.mov !== '' || d.fur !== '' || usado(d.v) || usado(d.l)) out[n] = d;
                });
                return out;
            },
            perioResumen() {
                let sitios = 0, s = 0, p = 0; const ps = [], nic = [];
                Object.values(this.perio).filter(d => !d.aus).forEach(d => ['v', 'l'].forEach(k => {
                    const c = d[k], usado = c.ps.some(v => v !== '') || c.s.includes(true) || c.p.includes(true);
                    if (!usado) return;
                    [0, 1, 2].forEach(i => { sitios++; s += c.s[i] ? 1 : 0; p += c.p[i] ? 1 : 0; if (c.ps[i] !== '') { ps.push(Number(c.ps[i])); nic.push(Number(c.ps[i]) + (Number(c.mg[i]) || 0)); } });
                }));
                const prom = a => a.length ? (a.reduce((x, y) => x + y, 0) / a.length).toFixed(1) : '—';
                return { sitios, sangrado: sitios ? (s / sitios * 100).toFixed(0) + '%' : '—', placa: sitios ? (p / sitios * 100).toFixed(0) + '%' : '—',
                    ps: prom(ps), b4: ps.filter(v => v >= 4 && v <= 5).length, b6: ps.filter(v => v >= 6).length, nic: prom(nic) };
            },
            perioTarjetas() {
                const r = this.perioResumen();
                return [{ t: 'Sangrado', v: r.sangrado, cl: 'text-rose-600' }, { t: 'Placa', v: r.placa, cl: 'text-sky-600' }, { t: 'PS promedio', v: r.ps + ' mm', cl: 'text-gray-800' },
                    { t: 'Bolsas 4-5 mm', v: r.b4, cl: 'text-amber-600' }, { t: 'Bolsas ≥ 6 mm', v: r.b6, cl: 'text-rose-600' }, { t: 'NIC promedio', v: r.nic + ' mm', cl: 'text-gray-800' },
                    { t: 'Sitios', v: r.sitios, cl: 'text-gray-500' }];
            },
        };
    };
</script>
@endonce

<details class="group" @if ($a->periodontograma) open @endif>
    <summary class="cursor-pointer font-bold text-gray-700 select-none">🦷 Periodontograma <span class="text-xs font-normal text-gray-400">(encías: sondaje, sangrado, placa, movilidad y furcas — toca para abrir)</span></summary>
    <div class="mt-3 space-y-3">
        <p class="text-xs text-gray-500">Escribe los milímetros y pasa al siguiente con <b>Tab</b>. <b>PS</b> = profundidad de sondaje · <b>MG</b> = margen gingival (positivo si hay recesión, negativo si hay agrandamiento) ·
            <b>NIC</b> = PS + MG. Toca el <b>número</b> del diente si está ausente. Sangrado ● rojo · Placa ● azul. PS de 4-5 mm sale amarillo y de 6 mm o más, rojo.</p>
        <div class="grid grid-cols-3 sm:grid-cols-7 gap-2 text-center">
            <template x-for="k in perioTarjetas()" :key="k.t">
                <div class="rounded-xl bg-gray-50 py-2"><p class="text-lg font-black" :class="k.cl" x-text="k.v"></p><p class="text-[10px] font-semibold uppercase text-gray-500" x-text="k.t"></p></div>
            </template>
        </div>
        @foreach ($arcadas as $arcada => [$der, $izq, $interna])
            <div class="overflow-x-auto rounded-xl border">
                <table class="text-[11px] border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="sticky left-0 bg-gray-50 px-2 py-1 text-left min-w-[86px]">{{ $arcada }}</th>
                            @foreach (array_merge($der, $izq) as $n)
                                <th class="px-0.5 py-1 {{ $n === 11 || $n === 41 ? 'border-r-2 border-gray-400' : '' }}">
                                    <button type="button" @click="perio[{{ $n }}].aus = !perio[{{ $n }}].aus" class="w-[70px] rounded font-bold py-0.5"
                                            :class="perio[{{ $n }}].aus ? 'bg-gray-700 text-white line-through' : 'bg-teal-50 text-teal-800'" title="Tocar si el diente está ausente">{{ $n }}</button></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (['mov' => 'Movilidad', 'fur' => 'Furca'] as $k => $titulo)
                            <tr>
                                <td class="sticky left-0 bg-white px-2 font-semibold text-gray-500">{{ $titulo }} <span class="font-normal">0-3</span></td>
                                @foreach (array_merge($der, $izq) as $n)
                                    <td class="text-center py-0.5 {{ $n === 11 || $n === 41 ? 'border-r-2 border-gray-400' : '' }}">
                                        <input x-show="!perio[{{ $n }}].aus" x-model="perio[{{ $n }}].{{ $k }}" inputmode="numeric" maxlength="1" class="{{ $celda }}"></td>
                                @endforeach
                            </tr>
                        @endforeach
                        @foreach (['v' => 'Vestibular', 'l' => $interna] as $cara => $nombreCara)
                            <tr><td colspan="17" class="sticky left-0 bg-teal-600 text-white font-bold px-2 py-0.5">{{ $nombreCara }}</td></tr>
                            @foreach (['s' => 'Sangrado', 'p' => 'Placa', 'mg' => 'MG', 'ps' => 'PS', 'nic' => 'NIC'] as $k => $titulo)
                                <tr class="{{ $k === 'ps' ? 'bg-teal-50/40' : '' }}">
                                    <td class="sticky left-0 bg-white px-2 font-semibold {{ $k === 'ps' ? 'text-teal-800' : 'text-gray-500' }}">{{ $titulo }}</td>
                                    @foreach (array_merge($der, $izq) as $n)
                                        <td class="py-0.5 {{ $n === 11 || $n === 41 ? 'border-r-2 border-gray-400' : '' }}">
                                            <div class="flex justify-center gap-px" x-show="!perio[{{ $n }}].aus">
                                                @foreach ([0, 1, 2] as $i)
                                                    @if ($k === 's' || $k === 'p')
                                                        <button type="button" @click="perio[{{ $n }}].{{ $cara }}.{{ $k }}[{{ $i }}] = !perio[{{ $n }}].{{ $cara }}.{{ $k }}[{{ $i }}]"
                                                                class="w-[22px] h-[18px] rounded-full border text-[12px] leading-none"
                                                                :class="perio[{{ $n }}].{{ $cara }}.{{ $k }}[{{ $i }}] ? '{{ $k === 's' ? 'bg-rose-500 border-rose-500' : 'bg-sky-500 border-sky-500' }}' : 'bg-white border-gray-300'"></button>
                                                    @elseif ($k === 'nic')
                                                        <span class="w-[22px] text-center text-gray-500" x-text="perioNic(perio[{{ $n }}].{{ $cara }}, {{ $i }})"></span>
                                                    @else
                                                        <input x-model="perio[{{ $n }}].{{ $cara }}.{{ $k }}[{{ $i }}]" inputmode="{{ $k === 'mg' ? 'text' : 'numeric' }}" maxlength="{{ $k === 'mg' ? 3 : 2 }}"
                                                               class="{{ $celda }}" @if ($k === 'ps') :class="perioCls(perio[{{ $n }}].{{ $cara }}.ps[{{ $i }}])" @endif>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
        <a href="{{ route('clinica.periodontograma', $a->ate_cli_id) }}" target="_blank" class="inline-block px-3 py-1.5 rounded-lg bg-white border text-xs font-semibold"><i class="fas fa-print"></i> Imprimir periodontograma (guarda antes)</a>
    </div>
</details>
