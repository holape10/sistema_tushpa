{{--
    App instalable (PWA): va dentro del <head>. $app = 'socio' para el portal del cliente/socio; si no, el sistema.
    Expone window.tushpaInstalar() y avisa con el evento "tushpa-instalable" cuando el navegador permite instalar.
--}}
@php $esPortal = ($app ?? null) === 'socio'; @endphp
<link rel="manifest" href="{{ route('pwa.manifest', $esPortal ? ['app' => 'socio'] : []) }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $esPortal ? 'Mi cuenta' : 'TUSHPA' }}">
<link rel="apple-touch-icon" href="{{ asset('imagenes/180.png') }}">
<script>
    (function () {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register(@json(asset('sw.js')), { scope: @json(url('/').'/') }).catch(() => {}));
        }
        let aviso = null;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            aviso = e;
            window.dispatchEvent(new CustomEvent('tushpa-instalable'));
        });
        window.addEventListener('appinstalled', () => { aviso = null; window.dispatchEvent(new CustomEvent('tushpa-instalada')); });
        window.tushpaPuedeInstalar = () => !!aviso;
        window.tushpaInstalar = async () => {
            if (!aviso) return false;
            aviso.prompt();
            const r = await aviso.userChoice;
            aviso = null;
            return r.outcome === 'accepted';
        };
    })();
</script>
