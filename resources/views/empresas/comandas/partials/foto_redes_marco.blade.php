{{-- Dibuja el marco de la foto para redes (comanda y vista previa en Editar sucursal): misma función en los dos lados --}}
@once
@push('scripts')
<script>
    /**
     * Foto + marco del local. estilo: DEGRADADO (sombra de color sobre la foto), BANDA (franja de color debajo) o
     * BORDE (marco completo tipo polaroid). Abajo van el logo, el nombre y el texto propio (sin texto: la fecha).
     */
    function marcoFotoRedes(img, logo, cfg) {
        const escala = Math.min(1, 1600 / Math.max(img.width, img.height));
        const w = Math.round(img.width * escala), h = Math.round(img.height * escala);
        const estilo = ['BANDA', 'BORDE'].includes(cfg.estilo) ? cfg.estilo : 'DEGRADADO';
        const color = /^#[0-9a-f]{6}$/i.test(cfg.color || '') ? cfg.color : '#111827';
        const [r, g, b] = [1, 3, 5].map(i => parseInt(color.slice(i, i + 2), 16));
        const claro = (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.6;
        const banda = Math.round(Math.min(w, h) * 0.17), margen = Math.round(banda * 0.18);
        const borde = estilo === 'BORDE' ? Math.round(Math.min(w, h) * 0.035) : 0;

        const c = document.createElement('canvas');
        c.width = w + borde * 2;
        c.height = h + borde + (estilo === 'DEGRADADO' ? 0 : banda);
        const x = c.getContext('2d');
        x.fillStyle = color;
        x.fillRect(0, 0, c.width, c.height);
        x.drawImage(img, borde, borde, w, h);

        const y0 = estilo === 'DEGRADADO' ? h - banda : borde + h;
        if (estilo === 'DEGRADADO') {
            const d = x.createLinearGradient(0, h - banda * 1.6, 0, h);
            d.addColorStop(0, `rgba(${r},${g},${b},0)`);
            d.addColorStop(1, `rgba(${r},${g},${b},0.85)`);
            x.fillStyle = d;
            x.fillRect(0, h - banda * 1.6, w, banda * 1.6);
        }
        const textoColor = estilo === 'DEGRADADO' || !claro ? '#ffffff' : '#111827';

        let izq = borde + margen;
        if (logo) {
            const caja = banda - margen;
            const ly = y0 + margen / 2;
            x.fillStyle = '#fff';
            x.beginPath();
            x.roundRect ? x.roundRect(izq, ly, caja, caja, caja * 0.2) : x.rect(izq, ly, caja, caja);
            x.fill();
            if (claro && estilo !== 'DEGRADADO') {
                x.strokeStyle = 'rgba(0,0,0,.12)';
                x.lineWidth = Math.max(1, banda * 0.015);
                x.stroke();
            }
            const lado = caja - margen;
            const k = Math.min(lado / logo.width, lado / logo.height);
            x.drawImage(logo, izq + (caja - logo.width * k) / 2, ly + (caja - logo.height * k) / 2, logo.width * k, logo.height * k);
            izq += caja + margen;
        }

        const ancho = c.width - izq - borde - margen;
        const fuente = "system-ui, -apple-system, 'Segoe UI', sans-serif";
        if (estilo === 'DEGRADADO') { x.shadowColor = 'rgba(0,0,0,.5)'; x.shadowBlur = 6; }
        x.fillStyle = textoColor;
        x.font = `800 ${Math.round(banda * 0.3)}px ${fuente}`;
        x.fillText(cfg.nombre || '', izq, y0 + banda * 0.52, ancho);
        x.globalAlpha = 0.85;
        x.font = `500 ${Math.round(banda * 0.19)}px ${fuente}`;
        const segunda = (cfg.texto || '').trim() || '📸 ' + new Date().toLocaleDateString('es-PE', { day: '2-digit', month: 'long', year: 'numeric' });
        x.fillText(segunda, izq, y0 + banda * 0.82, ancho);
        x.globalAlpha = 1;
        x.shadowBlur = 0;

        return c;
    }

    /** Carga una imagen (foto, logo); falla si no se puede leer */
    function cargarImagenRedes(src) {
        return new Promise((ok, falla) => { const i = new Image(); i.onload = () => ok(i); i.onerror = falla; i.src = src; });
    }
</script>
@endpush
@endonce
