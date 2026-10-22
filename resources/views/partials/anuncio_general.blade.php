{{--
    Anuncios generales del panel (ej. "Hoy SUNAT presenta fallas"): franja arriba de la pantalla.
    En el layout va dentro de la página; en las pantallas de venta a pantalla completa va flotando arriba.
    Si se puede cerrar, al cerrarlo no vuelve a salir en ese navegador (salvo que se edite el anuncio).
--}}
@once
    @php($anunciosGenerales = \App\Support\Anuncios::paraEmpresa())
    @if ($anunciosGenerales->isNotEmpty())
        <div id="anuncios-generales" style="{{ ($flotante ?? false) ? 'position:fixed;top:0;left:0;right:0;z-index:2147480000;' : 'position:relative;z-index:40;' }}font-family:system-ui,-apple-system,'Segoe UI',sans-serif">
            @foreach ($anunciosGenerales as $a)
                @php([$nombre, $fondo, $color, $icono] = \App\Support\Anuncios::TIPOS[$a->tipo] ?? \App\Support\Anuncios::TIPOS['INFO'])
                <div data-anuncio="{{ $a->id }}-{{ strtotime((string) $a->updated_at) }}"
                     style="background:{{ $fondo }};color:{{ $color }};border-bottom:1px solid rgba(0,0,0,.08);padding:8px 44px 8px 14px;text-align:center;font-size:14px;line-height:1.4;position:relative">
                    <span style="margin-right:6px">{{ $icono }}</span>{{ $a->mensaje }}
                    @if ($a->se_puede_cerrar)
                        <button type="button" title="Cerrar" aria-label="Cerrar"
                                onclick="try { localStorage.setItem('anuncio-cerrado-' + this.parentNode.dataset.anuncio, '1') } catch (e) {} this.parentNode.remove()"
                                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);border:0;background:transparent;color:inherit;font-size:22px;line-height:1;cursor:pointer;opacity:.7">&times;</button>
                    @endif
                </div>
            @endforeach
        </div>
        <script>
            document.querySelectorAll('#anuncios-generales [data-anuncio]').forEach(el => {
                try { if (localStorage.getItem('anuncio-cerrado-' + el.dataset.anuncio) && el.querySelector('button')) el.remove(); } catch (e) {}
            });
        </script>
    @endif
@endonce
