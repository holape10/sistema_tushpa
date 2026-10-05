<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<nav class="flex gap-1 overflow-x-auto bg-white rounded-2xl shadow-sm p-1 mb-4 print:hidden">
    @foreach ([['planilla.index', 'Planillas y boletas', 'fa-file-invoice-dollar'], ['planilla.trabajadores', 'Trabajadores', 'fa-users'], ['planilla.parametros', 'Parámetros', 'fa-sliders']] as [$r, $n, $i])
        <a href="{{ route($r) }}" class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold whitespace-nowrap {{ request()->routeIs($r) ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-indigo-50' }}"><i class="fas {{ $i }}"></i>{{ $n }}</a>
    @endforeach
</nav>
@include('empresas.partials.alert')
