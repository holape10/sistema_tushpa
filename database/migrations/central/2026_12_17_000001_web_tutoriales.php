<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tutoriales de tushpa.app/tutorial por rubro (restaurante, gimnasio, comercio...): pasos con texto e imagen y un video
 * opcional. El dueño los crea y edita en el panel (Tutoriales). Se dejan unos básicos escritos (las imágenes se suben luego).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasTable('web_tutoriales')) {
            return;
        }
        Schema::connection('central')->create('web_tutoriales', function (Blueprint $t) {
            $t->id();
            $t->string('rubro', 20)->default('TODOS')->index();   // TODOS o la clave del rubro (RESTOBAR, GIMNASIO...)
            $t->string('titulo', 150);
            $t->string('slug', 160)->unique();
            $t->string('resumen', 300)->nullable();
            $t->string('video_url', 255)->nullable();               // YouTube (opcional)
            $t->unsignedTinyInteger('minutos')->default(5);
            $t->json('pasos');                                      // [{"titulo", "texto", "imagen"}]
            $t->unsignedInteger('orden')->default(0);
            $t->boolean('activo')->default(true);
            $t->unsignedInteger('vistas')->default(0);
            $t->timestamps();
        });

        $p = fn (string $titulo, string $texto) => ['titulo' => $titulo, 'texto' => $texto, 'imagen' => null];
        $tutoriales = [
            ['TODOS', 'Primeros pasos en TUSHPA', 'Deja tu sistema listo para vender en 10 minutos.', 10, [
                $p('Ingresa a tu sistema', 'Entra a la dirección de tu empresa (tuempresa.tushpa.app) con el usuario y la contraseña que te llegaron al registrarte. Funciona en la PC, la tablet y el celular, sin instalar nada.'),
                $p('Revisa los datos de tu empresa', 'En el menú Mantenimiento → Empresas revisa tu RUC, razón social y dirección, y sube tu logo: así sale en tus boletas y facturas.'),
                $p('Revisa tu sucursal', 'En Mantenimiento → Sucursales revisa las series de tus comprobantes (por ejemplo B001 para boletas y F001 para facturas) y el comprobante que sale elegido al cobrar.'),
                $p('Crea tus categorías y productos', 'En Mantenimiento → Categorías crea tus grupos (Bebidas, Platos, Abarrotes...). Luego en Mantenimiento → Productos toca "+ Nuevo Producto", escribe el nombre y el precio, y guarda. Puedes ponerle foto.'),
                $p('Agrega a tu equipo', 'En Contactos → Usuarios crea un usuario para cada persona (caja, mozo, almacén...) con su propia clave. Cada uno ve solo lo que le corresponde.'),
            ]],
            ['TODOS', 'Abrir y cerrar la caja', 'Cada venta queda en un turno de caja: así sabes cuánto debe haber al final del día.', 5, [
                $p('Abre tu turno', 'En el menú entra a Caja. Escribe el "Fondo inicial de caja" (el sencillo con el que empiezas) y toca APERTURAR TURNO. Sin turno abierto el sistema no deja cobrar.'),
                $p('Registra entradas y salidas', 'Si sacas dinero para un gasto o entra dinero que no es venta, regístralo en la misma pantalla de Caja: así el cuadre sale exacto.'),
                $p('Cierra tu turno', 'Al terminar, en Caja cuenta los billetes y monedas (o escribe el total) y cierra el turno. El sistema te dice si sobró o faltó y te deja imprimir o descargar el reporte del turno.'),
            ]],
            ['TODOS', 'Emitir una boleta o factura', 'Tus comprobantes van solos a SUNAT y puedes enviarlos al cliente por WhatsApp.', 5, [
                $p('Elige el comprobante', 'Al cobrar, elige Boleta, Factura o Nota de venta. Para la factura escribe el RUC del cliente; para la boleta, su DNI (o déjala a "venta al portador" si es un monto pequeño).'),
                $p('El nombre se llena solo', 'Al escribir el DNI o RUC, el sistema busca el nombre o la razón social y la dirección. Solo revisa que estén bien.'),
                $p('Cobra', 'Elige cómo pagó (efectivo, Yape, tarjeta...) y toca cobrar. El comprobante se imprime y se envía a SUNAT automáticamente.'),
                $p('Revisa y reenvía', 'En Ventas → Panel Ventas ves cada comprobante con su estado en SUNAT, puedes descargar el PDF o enviarlo por WhatsApp al cliente.'),
            ]],
            ['RESTOBAR', 'Configura tus pisos y mesas', 'Arma el salón como es en la realidad: pisos o zonas y sus mesas.', 5, [
                $p('Crea tus pisos o zonas', 'En Restaurante → Pisos crea tus zonas: Salón principal, Terraza, Segundo piso...'),
                $p('Crea tus mesas', 'En Restaurante → Mesas agrega cada mesa con su nombre (M1, M2...) y el piso donde está.'),
                $p('Mira tu salón', 'Entra a Comandas: verás tus mesas por piso. En verde las libres y en color las ocupadas, con el tiempo que llevan.'),
            ]],
            ['RESTOBAR', 'Tomar pedidos con comandas', 'El mozo toma el pedido en el celular y la cocina lo recibe al instante.', 5, [
                $p('Elige la mesa', 'En Comandas toca una mesa libre. También puedes elegir Para llevar o Delivery.'),
                $p('Agrega los platos', 'Busca o elige la categoría y toca cada plato. Si el cliente pide algo especial, agrégale una nota (sin cebolla, poco ají...).'),
                $p('Envía la comanda', 'Toca ENVIAR: la comanda se imprime en la cocina o el bar (según la categoría) y aparece en la Pantalla Cocina. Para agregar más platos después, vuelve a tocar la mesa.'),
            ]],
            ['RESTOBAR', 'Cobrar una mesa', 'Cobra todo junto o por separado, con descuentos y propina si los usas.', 5, [
                $p('Abre la mesa ocupada', 'En Comandas toca la mesa. Verás lo que consumió y los botones PRECUENTA, SEPARADAS y COBRAR.'),
                $p('Imprime la precuenta (opcional)', 'Con PRECUENTA le llevas al cliente la cuenta antes de cobrar.'),
                $p('Cobra', 'Toca COBRAR, elige el comprobante y cómo pagó, y registra. Si pagan por separado usa SEPARADAS y cobra a cada persona lo suyo. Al terminar, la mesa queda libre.'),
            ]],
            ['GENERAL', 'Vender con el punto de venta', 'Vende rápido con clics o con lector de código de barras, y tu stock baja solo.', 5, [
                $p('Abre el punto de venta', 'Con tu turno de caja abierto, entra a PV. Verás tus productos por categoría con su precio.'),
                $p('Arma la venta', 'Toca los productos o escanea su código de barras. Cambia la cantidad con + y −; si necesitas, cambia el precio tocándolo.'),
                $p('Cobra', 'Elige el comprobante, escribe el DNI o RUC si lo piden, elige cómo pagó y toca COBRAR E IMPRIMIR. Si paga con billete, escribe con cuánto paga y el sistema te dice el vuelto.'),
                $p('Revisa tu stock', 'En Almacén → Stock Productos ves cuánto te queda de cada producto. Las compras que registras en Compras suben el stock.'),
            ]],
            ['GIMNASIO', 'Crear planes y vender membresías', 'Tus planes con su precio y días, y la venta con su comprobante.', 5, [
                $p('Crea tus planes', 'En Gimnasio toca Planes y crea cada uno con su nombre, días y precio (por ejemplo MENSUAL, 30 días). Puedes marcar si es promoción o VIP.'),
                $p('Registra al cliente', 'Toca Nuevo cliente, escribe su DNI (el nombre se llena solo) y su celular. Puedes tomarle una foto.'),
                $p('Véndele su plan', 'En su ficha, pestaña Vender plan: elige el plan, cómo pagó y cobra. Si paga por partes, marca "Pago por partes". El vencimiento se calcula solo.'),
            ]],
            ['GIMNASIO', 'Control de ingreso', 'En la entrada, el cliente marca con su código, DNI o QR y el sistema dice si puede pasar.', 3, [
                $p('Abre el control de ingreso', 'En una PC o tablet de la entrada abre Gimnasio → Control de ingreso y déjala abierta.'),
                $p('El cliente marca', 'Escribe su código o DNI, o acerca el QR de su carnet. Verde: adelante. Rojo: su plan venció y puede renovar o pagar la rutina del día.'),
                $p('Salida y asistencia', 'Al irse vuelve a marcar y queda su hora de salida. En Ingresos del día ves quién vino cada día.'),
            ]],
        ];
        $orden = 0;
        foreach ($tutoriales as [$rubro, $titulo, $resumen, $minutos, $pasos]) {
            DB::connection('central')->table('web_tutoriales')->insert([
                'rubro' => $rubro, 'titulo' => $titulo, 'slug' => Str::slug($titulo), 'resumen' => $resumen, 'minutos' => $minutos,
                'pasos' => json_encode($pasos, JSON_UNESCAPED_UNICODE), 'orden' => ++$orden, 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('web_tutoriales');
    }
};
