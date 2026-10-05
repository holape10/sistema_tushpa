@php
    $temas = [
        'success' => ['from-emerald-500 to-teal-600', 'fa-circle-check'],
        'info'    => ['from-sky-500 to-indigo-600', 'fa-hand'],
        'warning' => ['from-amber-400 to-orange-500', 'fa-circle-info'],
        'danger'  => ['from-rose-500 to-red-700', 'fa-triangle-exclamation'],
    ];
    [$fondo, $icono] = $temas[$tipo] ?? $temas['info'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistencia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gradient-to-br {{ $fondo }} flex items-center justify-center p-6">
    <div class="w-full max-w-sm bg-white rounded-3xl shadow-2xl p-8 text-center">
        <i class="fas {{ $icono }} text-6xl bg-gradient-to-br {{ $fondo }} bg-clip-text text-transparent"></i>
        <h1 class="mt-4 text-xl font-black text-slate-800 uppercase">{{ $titulo }}</h1>
        <p class="mt-3 text-slate-600 text-lg leading-snug">{{ $mensaje }}</p>
        <p class="mt-6 text-sm text-slate-400" id="hora"></p>
    </div>
    <script>
        document.getElementById('hora').textContent = new Date().toLocaleString('es-PE', { dateStyle: 'full', timeStyle: 'medium' });
    </script>
</body>
</html>
