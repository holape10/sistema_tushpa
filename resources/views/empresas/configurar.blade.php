<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Empresa - Sistema Tushpa</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('imagenes/512.png') }}" type="image/png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-gradient-to-r from-indigo-600 to-blue-600 py-6 px-4 sm:px-8 shadow-md">
            <div class="max-w-4xl mx-auto">
                <h1 class="text-white text-xl sm:text-2xl font-bold">Configuración inicial</h1>
                <p class="text-indigo-100 text-sm mt-1">Registra los datos de tu empresa para comenzar a usar el sistema</p>
            </div>
        </header>

        <main class="flex-1 py-8 px-4 sm:px-8">
            <div class="max-w-4xl mx-auto">

                @if (session('success'))
                    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3 text-sm">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div id="alertBox" class="hidden mb-4 rounded-lg px-4 py-3 text-sm"></div>

                <form id="formEmpresa" action="{{ route('empresa.store') }}" method="POST" class="space-y-6">
                    @csrf

                    <!-- Card: Datos de la empresa -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="bg-indigo-600 px-5 py-3">
                            <h2 class="text-white font-semibold text-sm tracking-wide uppercase">Registrar empresa principal</h2>
                        </div>
                        <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">

                            <div class="sm:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">RUC <span class="text-red-500">*</span></label>
                                <div class="flex gap-2">
                                    <input type="text" id="rucEmpresa" name="rucEmpresa" maxlength="11"
                                        value="{{ old('rucEmpresa') }}"
                                        class="flex-1 rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        placeholder="20123456789" required>
                                    <button type="button" id="btnConsultarRuc"
                                        class="shrink-0 inline-flex items-center px-3 rounded-lg bg-indigo-50 text-indigo-600 text-sm font-medium hover:bg-indigo-100 transition">
                                        Buscar
                                    </button>
                                </div>
                                <p id="rucMsg" class="text-xs mt-1"></p>
                            </div>

                            <div class="sm:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de negocio</label>
                                <select name="id_tipo_sistema" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="1">RESTAURANTE</option>
                                    <option value="2">COMERCIO / BODEGA</option>
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Razón Social <span class="text-red-500">*</span></label>
                                <input type="text" id="nomEmpresa" name="nomEmpresa" value="{{ old('nomEmpresa') }}"
                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre comercial</label>
                                <input type="text" name="NomComercial" value="{{ old('NomComercial') }}"
                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Dirección <span class="text-red-500">*</span></label>
                                <input type="text" id="dirEmpresa" name="dirEmpresa" value="{{ old('dirEmpresa') }}"
                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                            </div>

                            <div class="sm:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ubigeo</label>
                                <input type="text" id="ubigeo" name="ubigeo" value="{{ old('ubigeo') }}"
                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    placeholder="Se completa al buscar el RUC">
                            </div>

                        </div>
                    </div>

                    <!-- Card: Facturación electrónica -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="bg-blue-600 px-5 py-3">
                            <h2 class="text-white font-semibold text-sm tracking-wide uppercase">Facturación electrónica</h2>
                        </div>
                        <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de envío</label>
                                <select name="envio" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="1">SUNAT</option>
                                    <option value="2">OSE</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Producción</label>
                                <select name="produccion" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="0">BETA (pruebas)</option>
                                    <option value="1">PRODUCCIÓN</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ICBPER</label>
                                <input type="number" step="0.01" name="icbper" value="{{ old('icbper', 0.50) }}"
                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Formato de impresión</label>
                                <select name="formato" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="ticket">TICKET</option>
                                    <option value="a4">A4</option>
                                </select>
                            </div>

                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row justify-end gap-3">
                        <button type="submit" id="btnGuardar"
                            class="inline-flex justify-center items-center gap-2 px-6 py-3 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 active:scale-[0.98] transition shadow-sm">
                            Guardar y continuar
                        </button>
                    </div>

                </form>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('btnConsultarRuc').addEventListener('click', function () {
            const ruc = document.getElementById('rucEmpresa').value.trim();
            const msg = document.getElementById('rucMsg');
            msg.textContent = '';

            if (ruc.length !== 11) {
                msg.textContent = 'El RUC debe tener 11 dígitos';
                msg.className = 'text-xs mt-1 text-red-500';
                return;
            }

            fetch(`{{ url('api/ruc') }}/${ruc}`)
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        msg.textContent = data.error;
                        msg.className = 'text-xs mt-1 text-red-500';
                        return;
                    }
                    document.getElementById('nomEmpresa').value = data.nom ?? '';
                    document.getElementById('dirEmpresa').value = data.dir ?? '';
                    document.getElementById('ubigeo').value = data.ubigeo ?? '';
                    msg.textContent = 'Datos encontrados en SUNAT';
                    msg.className = 'text-xs mt-1 text-green-600';
                })
                .catch(() => {
                    msg.textContent = 'No se pudo consultar el RUC';
                    msg.className = 'text-xs mt-1 text-red-500';
                });
        });
    </script>
</body>
</html>