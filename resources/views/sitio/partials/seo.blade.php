{{-- Metas de SEO de la web pública: título, descripción, canonical, Open Graph, Twitter y datos estructurados (JSON-LD).
     Recibe: $seoTitulo, $seoDescripcion, $seoUrl (dirección canónica absoluta) y, opcionales, $seoJson, $seoImagen, $seoRobots, $seoTipo --}}
@php
    $seoRobots ??= 'index, follow';
    $seoImagen ??= asset('web/img/logo_tushpa.webp');
    $seoTipo ??= 'website';
@endphp
<title>{{ $seoTitulo }}</title>
<meta name="description" content="{{ $seoDescripcion }}">
<meta name="robots" content="{{ $seoRobots }}, max-image-preview:large">
<link rel="canonical" href="{{ $seoUrl }}">
<meta property="og:type" content="{{ $seoTipo }}">
<meta property="og:locale" content="es_PE">
<meta property="og:site_name" content="TUSHPA">
<meta property="og:title" content="{{ $seoTitulo }}">
<meta property="og:description" content="{{ $seoDescripcion }}">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:image" content="{{ $seoImagen }}">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $seoTitulo }}">
<meta name="twitter:description" content="{{ $seoDescripcion }}">
<meta name="twitter:image" content="{{ $seoImagen }}">
@isset($seoJson)
<script type="application/ld+json">{!! $seoJson !!}</script>
@endisset
