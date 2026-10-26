{{--
    Al final de cada página de tushpa.app: las promociones del panel (ventana o barra) y "Continuar con Google" (One Tap).
    Usa estilos propios para no depender del CSS de cada página.
--}}
@php
    $promos = \App\Support\Promociones::para(request()->path());
    $googleId = config('services.google.client_id');
    $conGoogle = $googleId && ! session('google') && in_array(\App\Support\Promociones::pagina(request()->path()), ['inicio', 'registro', 'carta', 'ciudades', 'tutorial'], true);
@endphp

@if ($promos->isNotEmpty())
    <style>
        .tp-barra { position: fixed; bottom: 0; left: 0; right: 0; z-index: 2000; color: #fff; font-family: Poppins, system-ui, sans-serif; font-size: 14px;
                    display: flex; align-items: center; justify-content: center; gap: 12px; padding: 10px 84px 10px 14px; box-shadow: 0 -4px 14px rgba(0,0,0,.2); flex-wrap: wrap; text-align: center; }
        .tp-barra a.tp-btn { background: rgba(255,255,255,.22); color: #fff; padding: 4px 12px; border-radius: 999px; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .tp-cerrar { position: absolute; right: 60px; top: 50%; transform: translateY(-50%); background: none; border: 0; color: inherit; font-size: 22px; cursor: pointer; line-height: 1; }
        .tp-fondo { position: fixed; inset: 0; z-index: 2100; background: rgba(15,23,42,.65); display: flex; align-items: center; justify-content: center; padding: 16px;
                    opacity: 0; transition: opacity .25s; font-family: Poppins, system-ui, sans-serif; }
        .tp-fondo.tp-ver { opacity: 1; }
        .tp-modal { position: relative; background: #fff; border-radius: 22px; overflow: hidden; width: 100%; max-width: 440px; text-align: center; box-shadow: 0 30px 70px -20px rgba(0,0,0,.6);
                    transform: translateY(20px) scale(.97); transition: transform .25s; }
        .tp-fondo.tp-ver .tp-modal { transform: none; }
        .tp-modal img { width: 100%; max-height: 300px; object-fit: cover; display: block; }
        .tp-modal .tp-cuerpo { padding: 20px 22px 22px; color: #0f172a; }
        .tp-modal h3 { margin: 0 0 6px; font-size: 22px; font-weight: 800; line-height: 1.2; }
        .tp-modal p { margin: 0 0 14px; color: #475569; font-size: 15px; white-space: pre-line; }
        .tp-modal a.tp-btn { display: inline-block; color: #fff; padding: 12px 26px; border-radius: 14px; font-weight: 700; text-decoration: none; font-size: 16px; }
        .tp-modal .tp-cerrar { top: 10px; right: 10px !important; transform: none; width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,.9); color: #0f172a; font-size: 20px; }
        /* Por ciudad: dónde vale y el WhatsApp de cada ciudad */
        .tp-ciudades { margin: 0 0 12px; font-size: 13px; font-weight: 700; color: #334155; }
        .tp-wa { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-bottom: 10px; }
        .tp-wa a { display: inline-flex; align-items: center; gap: 6px; background: #16a34a; color: #fff; padding: 10px 16px; border-radius: 12px; font-weight: 700; text-decoration: none; font-size: 14px; }
        .tp-barra .tp-wa { margin: 0; }
        .tp-barra .tp-wa a { padding: 4px 12px; border-radius: 999px; font-size: 13px; }
    </style>
    @foreach ($promos as $p)
        @php
            $destino = route('sitio.promo.clic', $p->id);
            $ciudades = $p->ciudades_lista ?? [];
            $conWhatsapp = array_filter($ciudades, fn ($c) => $c['whatsapp']);
            // Con WhatsApp por ciudad, el botón general solo sale si tiene su propio enlace o página
            $botonGeneral = ! $conWhatsapp || \App\Support\Promociones::destino($p);
        @endphp
        @if ($p->tipo === 'BARRA')
            <div class="tp-barra" id="tp-promo-{{ $p->id }}" style="background: {{ $p->color }}; display: none" data-promo="{{ $p->id }}" data-frecuencia="{{ $p->frecuencia }}" data-retraso="0">
                <b>{{ $p->titulo }}</b>@if ($p->texto)<span style="opacity:.9">{{ $p->texto }}</span>@endif
                @if ($ciudades)<span style="opacity:.9">📍 {{ collect($ciudades)->pluck('nombre')->implode(' · ') }}</span>@endif
                @if ($conWhatsapp)
                    <span class="tp-wa">@foreach ($conWhatsapp as $c)<a href="{{ $destino }}?ciudad={{ \App\Support\Promociones::slug($c['nombre']) }}" rel="nofollow">💬 {{ $c['nombre'] }}</a>@endforeach</span>
                @endif
                @if ($botonGeneral && (\App\Support\Promociones::destino($p) || $p->boton_texto))<a class="tp-btn" href="{{ $destino }}">{{ $p->boton_texto ?: 'Ver más' }}</a>@endif
                <button type="button" class="tp-cerrar" aria-label="Cerrar" data-cerrar>×</button>
            </div>
        @else
            <div class="tp-fondo" id="tp-promo-{{ $p->id }}" style="display: none" data-promo="{{ $p->id }}" data-frecuencia="{{ $p->frecuencia }}" data-retraso="{{ (int) $p->retraso }}">
                <div class="tp-modal" role="dialog" aria-label="{{ $p->titulo }}">
                    <button type="button" class="tp-cerrar" aria-label="Cerrar" data-cerrar>×</button>
                    @if ($p->imagen)<a href="{{ $botonGeneral ? $destino : '#' }}" @unless ($botonGeneral) onclick="return false" @endunless><img src="{{ asset($p->imagen) }}" alt="{{ $p->titulo }}"></a>@endif
                    <div class="tp-cuerpo">
                        <h3>{{ $p->titulo }}</h3>
                        @if ($p->texto)<p>{{ $p->texto }}</p>@endif
                        @if ($ciudades)<p class="tp-ciudades">📍 Válido en: {{ collect($ciudades)->pluck('nombre')->implode(' · ') }}</p>@endif
                        @if ($conWhatsapp)
                            <div class="tp-wa">
                                @foreach ($conWhatsapp as $c)
                                    <a href="{{ $destino }}?ciudad={{ \App\Support\Promociones::slug($c['nombre']) }}" rel="nofollow">💬 {{ count($conWhatsapp) > 1 ? $c['nombre'] : 'Escríbenos por WhatsApp' }}</a>
                                @endforeach
                            </div>
                        @endif
                        @if ($botonGeneral)<a class="tp-btn" href="{{ $destino }}" style="background: {{ $p->color }}">{{ $p->boton_texto ?: 'Ver oferta' }}</a>@endif
                    </div>
                </div>
            </div>
        @endif
    @endforeach
    <script>
        (function () {
            const hoy = new Date().toISOString().slice(0, 10);
            const leer = (k, s) => { try { return (s ? sessionStorage : localStorage).getItem(k); } catch (e) { return null; } };
            const poner = (k, v, s) => { try { (s ? sessionStorage : localStorage).setItem(k, v); } catch (e) {} };
            document.querySelectorAll('[data-promo]').forEach(el => {
                const id = el.dataset.promo, f = el.dataset.frecuencia, clave = 'tp_promo_' + id;
                // Cada cuánto: siempre, una vez por visita, una vez al día o una sola vez
                if ((f === 'SESION' && leer(clave, true)) || (f === 'DIA' && leer(clave) === hoy) || (f === 'UNA_VEZ' && leer(clave))) return;
                setTimeout(() => {
                    el.style.display = 'flex';
                    requestAnimationFrame(() => el.classList.add('tp-ver'));
                    if (el.classList.contains('tp-barra')) document.body.style.paddingBottom = el.offsetHeight + 'px';
                    f === 'SESION' ? poner(clave, '1', true) : poner(clave, hoy);
                    fetch(@js(url('/promo')) + '/' + id + '/vista', { method: 'POST', headers: { 'X-CSRF-TOKEN': @js(csrf_token()) } }).catch(() => {});
                }, (Number(el.dataset.retraso) || 0) * 1000);
                const cerrar = () => { el.style.display = 'none'; if (el.classList.contains('tp-barra')) document.body.style.paddingBottom = ''; };
                el.querySelectorAll('[data-cerrar]').forEach(b => b.addEventListener('click', cerrar));
                if (el.classList.contains('tp-fondo')) el.addEventListener('click', e => { if (e.target === el) cerrar(); });
                document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrar(); });
            });
        })();
    </script>
@endif

@if ($conGoogle)
    {{-- Google One Tap: "Continuar como …" arriba a la derecha; llena el registro con su nombre y correo --}}
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <div id="g_id_onload" data-client_id="{{ $googleId }}" data-callback="tushpaGoogle" data-auto_prompt="true" data-context="signup"
         data-itp_support="true" data-cancel_on_tap_outside="true"></div>
    <script>
        function tushpaGoogle(r) {
            fetch(@js(route('sitio.google')), {
                method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                body: JSON.stringify({ credential: r.credential, desde: location.pathname }),
            }).then(x => x.json()).then(d => { if (d.ok) location.href = d.redirect; }).catch(() => {});
        }
    </script>
@endif
