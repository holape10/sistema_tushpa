<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Empresa - Sistema Tushpa</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('imagenes/512.png') }}" type="image/png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-gradient-to-r from-indigo-600 to-blue-600 py-6 px-4 sm:px-8 shadow-md">
            <div class="max-w-6xl mx-auto">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-white text-xl sm:text-2xl font-bold">Editar Empresa</h1>
                        <p class="text-indigo-100 text-sm mt-1">RUC: {{ $empresa->IdEmpresa }}</p>
                    </div>
                    <a href="{{ route('empresas.index') }}" 
                       class="inline-flex items-center gap-2 px-4 py-2 bg-white text-indigo-600 rounded-lg font-semibold hover:bg-indigo-50 transition shadow-sm">
                        <i class="fas fa-arrow-left"></i>
                        <span class="hidden sm:inline">Volver</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="flex-1 py-8 px-4 sm:px-8">
            <div class="max-w-6xl mx-auto">

                @if (session('success'))
                    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3 text-sm">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                        {{ session('error') }}
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

                <form action="{{ route('empresas.update', $empresa->IdEmpresa) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <!-- SECCIÓN 1: DATOS GENERALES -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="bg-slate-700 px-5 py-3">
                            <h2 class="text-white font-semibold text-sm tracking-wide uppercase">
                                <i class="fas fa-industry mr-2"></i>EDITAR EMPRESA
                            </h2>
                        </div>
                        <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">RUC <span class="text-red-500">*</span></label>
                                <input type="text" name="rucEmpresa" value="{{ $empresa->IdEmpresa }}" 
                                       readonly 
                                       class="w-full rounded-lg border-gray-300 text-gray-600 text-sm font-bold cursor-not-allowed" 
                                       style="background: #eee;">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Negocio <span class="text-red-500">*</span></label>
                                @if(count($tipos_sistemas) > 0)
                                    <select name="id_tipo_sistema" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        @foreach($tipos_sistemas as $tipo)
                                            <option value="{{ $tipo->id_tipo }}" {{ $empresa->id_tipo_sistema == $tipo->id_tipo ? 'selected' : '' }}>
                                                {{ $tipo->nombre_sistema }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <select name="id_tipo_sistema" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="1" {{ $empresa->id_tipo_sistema == 1 ? 'selected' : '' }}>RESTAURANTE</option>
                                        <option value="2" {{ $empresa->id_tipo_sistema == 2 ? 'selected' : '' }}>COMERCIO / BODEGA</option>
                                    </select>
                                @endif
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Razón Social <span class="text-red-500">*</span></label>
                                <input type="text" name="nomEmpresa" value="{{ old('nomEmpresa', $empresa->NomEmpresa) }}" 
                                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                                <input type="text" name="dirEmpresa" value="{{ old('dirEmpresa', $empresa->DirEmpresa) }}" 
                                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>

                        </div>
                    </div>

                    <!-- SECCIÓN 2: FACTURACIÓN ELECTRÓNICA -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="bg-slate-600 px-5 py-3">
                            <h2 class="text-white font-semibold text-sm tracking-wide uppercase">
                                <i class="fas fa-file-code mr-2"></i>FACTURACIÓN ELECTRÓNICA
                            </h2>
                        </div>
                        <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Envío</label>
                                @if(count($tip_env_fac) > 0)
                                    <select name="tip_env_fac" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        @foreach($tip_env_fac as $tef)
                                            <option value="{{ $tef->tip_env_fac_id }}" {{ $empresa->tip_env_fac_id == $tef->tip_env_fac_id ? 'selected' : '' }}>
                                                {{ $tef->tip_env_fac_des }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <select name="tip_env_fac" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="1" {{ $empresa->tip_env_fac_id == 1 ? 'selected' : '' }}>SUNAT</option>
                                        <option value="2" {{ $empresa->tip_env_fac_id == 2 ? 'selected' : '' }}>OSE</option>
                                    </select>
                                @endif
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Producción</label>
                                <select name="produccion" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="0" {{ $empresa->produccion == '0' ? 'selected' : '' }}>BETA</option>
                                    <option value="1" {{ $empresa->produccion == '1' ? 'selected' : '' }}>PRODUCCIÓN</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Envío a SUNAT</label>
                                <select name="envio" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="1" {{ $empresa->tipo_envio == '1' ? 'selected' : '' }}>ENVÍO AUTOMÁTICO</option>
                                    <option value="0" {{ $empresa->tipo_envio == '0' ? 'selected' : '' }}>ENVÍO MANUAL</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Usuario SUNAT</label>
                                <input type="text" name="txtWsUsuario" value="{{ old('txtWsUsuario', $empresa->wsusuario) }}" 
                                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña SUNAT</label>
                                <div class="relative">
                                    <input type="password" name="txtWsContrasena" id="txtWsContrasena" value="{{ old('txtWsContrasena', $empresa->claveSunat) }}" 
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <button type="button" onclick="togglePassword('txtWsContrasena', this)" 
                                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ICBPER</label>
                                <input type="number" step="0.01" name="icbper" min="0" value="{{ old('icbper', $empresa->icbper) }}" 
                                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>

                        </div>
                    </div>

                    <!-- SECCIÓN 2b: SIRE (API SUNAT) -->
                    @php $sireListo = \App\Support\Sunat\Sire::configurado($empresa); @endphp
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="bg-emerald-700 px-5 py-3 flex items-center justify-between gap-3">
                            <h2 class="text-white font-semibold text-sm tracking-wide uppercase">
                                <i class="fas fa-book mr-2"></i>Credenciales de API SUNAT · SIRE y guías de remisión
                            </h2>
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $sireListo ? 'bg-white text-emerald-700' : 'bg-amber-300 text-amber-900' }}">
                                {{ $sireListo ? 'Configurado' : 'Sin configurar' }}
                            </span>
                        </div>
                        <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ID <span class="text-gray-400 font-normal">(client_id)</span></label>
                                <input type="text" name="sire_client_id" value="{{ old('sire_client_id', $empresa->client_id) }}" autocomplete="off"
                                       placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CLAVE <span class="text-gray-400 font-normal">(client_secret)</span></label>
                                <div class="relative">
                                    <input type="password" name="sire_client_secret" id="sire_client_secret" autocomplete="new-password"
                                           placeholder="{{ $empresa->client_secret ? '•••••••• guardada (vacío = no cambiar)' : 'Pega la CLAVE de SUNAT' }}"
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono pr-9">
                                    <button type="button" onclick="togglePassword('sire_client_secret', this)"
                                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Usuario SOL para el SIRE</label>
                                <input type="text" name="sire_usuario" value="{{ old('sire_usuario', $empresa->sire_usuario) }}" maxlength="30" autocomplete="off"
                                       placeholder="Vacío = {{ $empresa->wsusuario ?: 'el de facturación' }}"
                                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm uppercase">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Clave SOL para el SIRE</label>
                                <div class="relative">
                                    <input type="password" name="sire_clave" id="sire_clave" autocomplete="new-password"
                                           placeholder="{{ $empresa->sire_clave ? '•••••••• guardada (vacío = no cambiar)' : 'Vacío = la de facturación' }}"
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm pr-9">
                                    <button type="button" onclick="togglePassword('sire_clave', this)"
                                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="sm:col-span-2 text-xs text-gray-500 bg-gray-50 rounded-lg px-4 py-3 leading-relaxed">
                                <i class="fas fa-info-circle mr-1 text-emerald-600"></i>
                                En SUNAT SOL: <strong>Empresas → Credenciales de API SUNAT → Gestión Credenciales de API SUNAT</strong>; copia el <strong>ID</strong> y la <strong>CLAVE</strong>
                                y marca los permisos del SIRE. Al guardar se prueba la conexión con SUNAT. La CLAVE y la clave SOL se guardan cifradas.
                                @if ($sireListo)
                                    <a href="{{ route('sire.index', 'ventas') }}" class="ml-1 font-semibold text-emerald-700 hover:underline">Ir al SIRE Ventas →</a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: CERTIFICADO DIGITAL -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="bg-blue-600 px-5 py-3">
                            <h2 class="text-white font-semibold text-sm tracking-wide uppercase">
                                <i class="fas fa-key mr-2"></i>CERTIFICADO DIGITAL
                            </h2>
                        </div>
                        <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Certificado Digital (.pfx)</label>
                                <input type="file" name="txtCertificado" accept=".pfx,.p12" 
                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @if($empresa->certificado)
                                    <p class="text-xs text-green-600 mt-1">
                                        <i class="fas fa-check-circle"></i> Certificado actual: {{ $empresa->certificado }}
                                    </p>
                                @endif
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña Certificado</label>
                                <div class="relative">
                                    <input type="password" name="txtPassCert" id="txtPassCert" value="{{ old('txtPassCert', $empresa->passcert) }}" 
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <button type="button" onclick="togglePassword('txtPassCert', this)" 
                                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Válido Desde</label>
                                <input type="date" name="fecini" value="{{ old('fecini', $empresa->fec_ini_cer) }}" 
                                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Válido Hasta</label>
                                <input type="date" name="fecfin" value="{{ old('fecfin', $empresa->fec_fin_cer) }}" 
                                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>

                        </div>
                    </div>

                    <!-- SECCIÓN 4: CONFIGURACIÓN DE IMPRESIÓN -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="bg-gray-600 px-5 py-3">
                            <h2 class="text-white font-semibold text-sm tracking-wide uppercase">
                                <i class="fas fa-print mr-2"></i>CONFIGURACIÓN DE IMPRESIÓN
                            </h2>
                        </div>
                        <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">IMP. PEDIDOS</label>
                                <input type="number" name="imp_pedido" min="1" value="{{ old('imp_pedido', $empresa->imp_pedido) }}" 
                                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">IMP. COMPROBANTES</label>
                                <input type="number" name="imp_venta" min="1" value="{{ old('imp_venta', $empresa->imp_venta) }}" 
                                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Formato</label>
                                <select name="formato" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="TICKET" {{ $empresa->formato == 'TICKET' ? 'selected' : '' }}>TICKET</option>
                                    <option value="A4" {{ $empresa->formato == 'A4' ? 'selected' : '' }}>A4</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ticket-Pantalla</label>
                                <select name="ticket_pantalla" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="1" {{ $empresa->ticket_pantalla == '1' ? 'selected' : '' }}>SI</option>
                                    <option value="0" {{ $empresa->ticket_pantalla == '0' ? 'selected' : '' }}>NO</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Logo Login</label>
                                <input type="file" name="logologin" accept="image/*" 
                                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @if($empresa->LogEmpresa)
                                    <p class="text-xs text-green-600 mt-1">
                                        <i class="fas fa-check-circle"></i> Logo actual: {{ $empresa->LogEmpresa }}
                                    </p>
                                @endif
                            </div>

                        </div>
                    </div>

                    <!-- SECCIÓN 5: CORREO DE ENVÍO -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="bg-teal-600 px-5 py-3">
                            <h2 class="text-white font-semibold text-sm tracking-wide uppercase">
                                <i class="fas fa-envelope mr-2"></i>CONFIGURACIÓN DE CORREO DE ENVÍO
                            </h2>
                        </div>
                        <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Correo de Envío</label>
                                <input type="email" name="correo_envio" value="{{ old('correo_envio', $empresa->correo_envio) }}" 
                                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña de Envío</label>
                                <div class="relative">
                                    <input type="password" name="contrasena_envio" id="contrasena_envio" value="{{ old('contrasena_envio', $empresa->contrasena_envio) }}" 
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <button type="button" onclick="togglePassword('contrasena_envio', this)" 
                                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- BOTONES DE ACCIÓN -->
                    <div class="flex flex-col sm:flex-row justify-end gap-3">
                        <a href="{{ route('empresas.index') }}" 
                           class="inline-flex justify-center items-center gap-2 px-6 py-3 rounded-xl bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300 transition">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </a>
                        <button type="submit" 
                                class="inline-flex justify-center items-center gap-2 px-6 py-3 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700 active:scale-[0.98] transition shadow-sm">
                            <i class="fas fa-save"></i>
                            Guardar Cambios
                        </button>
                    </div>

                </form>
            </div>
        </main>
    </div>

    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>

</body>
</html>