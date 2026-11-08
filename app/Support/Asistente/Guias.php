<?php

namespace App\Support\Asistente;

use App\Support\Tutoriales;
use Illuminate\Support\Str;

/**
 * Cómo se hace cada cosa en el sistema: guías paso a paso que el asistente le explica al usuario (con los nombres reales
 * de las opciones del menú y de los botones). Si en el panel TUSHPA hay un tutorial con video sobre el tema, también va.
 * Cuando se agrega o cambia una pantalla, se actualiza aquí su guía.
 */
class Guias
{
    /** clave => [título, palabras para encontrarla, pasos] */
    public const GUIAS = [
        'enviar_entre_sedes' => ['Enviar productos o insumos del almacén a otra sede (sucursal)',
            'enviar mandar pasar transferir almacen sede sucursal restaurante reponer reposicion otro local',
            "Sirve para que una sede (por ejemplo el almacén central) le mande productos a otra (por ejemplo un restaurante).\n"
            ."1. Estando en la sede que envía, entra a Almacén › Requerimientos del día y toca \"🚚 Enviar a otra sede\".\n"
            ."2. Elige a qué sede envías y de qué almacén sale.\n"
            ."3. Pon la cantidad de cada producto (por defecto se ven solo los que tienen stock; no deja enviar más de lo que hay) y toca \"Enviar\".\n"
            ."4. El stock sale del almacén en ese momento y el envío queda \"En camino\".\n"
            ."5. En la otra sede aparece en su Inicio como \"🚚 POR RECIBIR\" (y al administrador le llega un aviso al celular si lo activó).\n"
            ."6. Allí lo abren, revisan cuánto llegó (si algo faltó o se rompió, lo corrigen) y tocan \"Confirmar lo que llegó\": entra a su stock.\n"
            .'Importante: cada sede tiene su propio catálogo. Si la sede que recibe no tiene el producto, el sistema se lo crea solo, copiado (código, nombre, unidad, costo y categoría); '
            .'conviene revisar después su precio de venta. Si ya lo tiene (mismo código o mismo nombre), no se duplica.'],
        'pedir_a_otra_sede' => ['Pedir productos a otra sede (por ejemplo al almacén central)',
            'pedir solicitar requerimiento sede central almacen otra sucursal despachar recibir',
            "1. En la sede que necesita, entra a Almacén › Requerimientos del día › \"+ Nuevo requerimiento\".\n"
            ."2. En \"¿Quién lo consigue?\" elige \"Pedir a la sede …\" (la central). Marca \"Es urgente\" si corre prisa.\n"
            ."3. Pon las cantidades (vienen sugeridas según el stock y el consumo) y envía.\n"
            ."4. La otra sede lo ve como \"te lo pide …\", elige de qué almacén sale, pone cuánto envía y toca \"Despachar\".\n"
            ."5. La sede que pidió lo ve \"En camino\" y, al llegar, confirma lo que llegó: entra a su stock.\n"
            .'Ojo: así solo se piden productos que la sede ya tiene creados. Para mandar productos nuevos, que los envíe la central con "Enviar a otra sede".'],
        'requerimiento_compra' => ['Requerimiento del día: pedir lo que falta para comprarlo (mercado o proveedor)',
            'requerimiento compra mercado faltante falta pedido cocina almacenero comprar lista factura',
            "1. Cocina o almacén entra a Almacén › Requerimientos del día › \"+ Nuevo requerimiento\".\n"
            ."2. Se ve lo que se acabó, lo que llegó a su mínimo y lo que se acabaría en 2 días; la cantidad viene sugerida.\n"
            ."3. Deja \"Se compra\", ajusta cantidades, agrega notas y envía. Se puede imprimir.\n"
            ."4. El administrador lo ve en su Inicio (\"Requerimientos por comprar\"); en el mercado lo abre en el celular y anota cuánto compró y a qué precio (0 si no había).\n"
            ."5. Elige el comprobante que le dieron (factura, boleta o sin comprobante) y toca \"Registrar como compra\": se abre la compra ya llena; al guardarla sube el stock.\n"
            .'Con factura el IGV sirve de crédito fiscal; sin comprobante el gasto no es deducible.'],
        'produccion_del_dia' => ['Planificar la producción del día y pedir los insumos que faltan',
            'produccion planificar platos recetas insumos cuanto necesito cocinar mañana',
            "1. Entra a Almacén › Requerimientos del día › \"🍳 Producción del día\".\n"
            ."2. Pon cuántos platos vas a preparar (solo salen los que tienen receta) y toca \"Calcular insumos\".\n"
            ."3. Verás cuánto necesitas de cada insumo, cuánto hay y cuánto falta.\n"
            .'4. Toca "Pedir lo que falta": se arma el requerimiento con esas cantidades.'],
        'inventario_ciego' => ['Inventario ciego (contar el almacén sin ver el stock)',
            'inventario ciego contar conteo fisico diferencias faltante sobrante ajustar stock',
            "1. El almacenero entra a Almacén › Inventario ciego › \"+ Nuevo conteo\".\n"
            ."2. Cuenta lo que hay físicamente y lo escribe; no ve cuánto dice el sistema. Lo que no cuente queda igual.\n"
            ."3. El administrador entra al conteo y ve las diferencias en cantidad y en soles (faltante y sobrante).\n"
            ."4. Decide \"Ajustar el stock\" (se aplica la diferencia sobre el stock de ahora, sin perder lo vendido mientras tanto) o \"Descartar\" para que cuenten de nuevo.\n"
            .'Recomendado: una vez por semana.'],
        'control_insumos' => ['Control de insumos (lo que se debió gastar vs. lo que faltó)',
            'control insumos perdida robo desperdicio desviacion merma recetas',
            "1. Entra a Almacén › Control de insumos y elige almacén y fechas.\n"
            ."2. Por insumo verás lo que se debió gastar según las recetas de lo vendido, las mermas registradas y lo que faltó o sobró en los conteos.\n"
            .'3. Lo que faltó sin explicación sale en rojo como pérdida en soles. Para que sea exacto, cuenten el almacén (inventario ciego) y registren las mermas.'],
        'transferencia_mismo_local' => ['Transferir entre almacenes de la misma sede',
            'transferencia almacenes misma sede barra cocina almacen principal mover stock',
            "1. Entra a Almacén › Transferencias › nueva transferencia (solo administrador o caja).\n"
            ."2. Elige el almacén de origen y el de destino (de la misma sede), y los productos con su cantidad.\n"
            ."3. Elige si es inmediata (entra al destino al toque) o en tránsito (entra cuando la reciban).\n"
            .'Para mandar a OTRA sede se usa "Enviar a otra sede" en Requerimientos del día.'],
        'ordenes_compra' => ['Órdenes de compra con aprobación',
            'orden de compra proveedor aprobar aprobacion pedido proveedor',
            "1. Entra a Compras › Órdenes de compra › \"+ Nueva orden\", elige el proveedor y los productos.\n"
            ."2. El administrador la aprueba o la rechaza (lo que crea el administrador nace aprobado).\n"
            .'3. Una orden aprobada se convierte en compra con un clic (el formulario viene lleno). La compra puede quedar "en tránsito" hasta que llegue la mercadería.'],
        'usuarios_y_sedes' => ['Usuarios por sede y cambiar de sede',
            'usuario usuarios rol roles cocinero almacenero mozo cajero sede sucursal cambiar trabajar aqui acceso',
            "1. Los usuarios se crean en Contactos › Usuarios: se elige su rol (Cajero, Mozo, Cocinero, Almacenero…) y su sede; las opciones del menú se marcan solas según el rol.\n"
            ."2. Cada usuario trabaja solo en su sede: no ve ni toca las otras.\n"
            ."3. Solo el administrador cambia de sede: en Mantenimiento › Sucursales toca \"Trabajar aquí\" en la sede que quiere ver.\n"
            .'4. En los reportes, el administrador puede ver una sede o todas juntas.'],
        'mermas' => ['Registrar mermas (lo que se bota o se malogra)',
            'merma mermas malogro vencido botar desperdicio',
            "1. Entra a Restaurante › Mermas.\n"
            ."2. Elige el producto o insumo, la cantidad y el motivo, y guarda: sale del stock.\n"
            .'3. Así el control de insumos distingue lo que se botó (registrado) de lo que falta sin explicación.'],
    ];

    /** Definición de la herramienta para la API */
    public static function definicion(): array
    {
        return [
            'name' => 'ayuda_sistema',
            'description' => 'Guías paso a paso de CÓMO HACER algo en el sistema (dónde está cada opción y qué botones tocar). Úsala cuando el usuario pregunte cómo se hace algo. '
                .'Temas: '.collect(self::GUIAS)->map(fn ($g, $clave) => "{$clave} ({$g[0]})")->implode('; ').'. Si no sabes el tema exacto, manda palabras clave en "buscar".',
            'inputSchema' => ['type' => 'object', 'properties' => [
                'tema' => ['type' => 'string', 'enum' => array_keys(self::GUIAS)],
                'buscar' => ['type' => 'string', 'description' => 'Palabras de lo que quiere hacer el usuario (si no hay un tema exacto).'],
            ]],
        ];
    }

    /** @return array<string, mixed> la guía pedida (o las que coinciden con lo buscado) y los tutoriales con video del tema */
    public static function buscar(array $input): array
    {
        $tema = (string) ($input['tema'] ?? '');
        $texto = Str::lower(Str::ascii(trim((string) ($input['buscar'] ?? ''))));
        $guias = isset(self::GUIAS[$tema]) ? [$tema => self::GUIAS[$tema]] : collect(self::GUIAS)
            ->map(fn ($g) => [$g, collect(preg_split('/\s+/', $texto, -1, PREG_SPLIT_NO_EMPTY))
                ->filter(fn ($p) => mb_strlen($p) > 2 && str_contains(Str::lower(Str::ascii($g[0].' '.$g[1])), $p))->count()])
            ->filter(fn ($x) => $x[1] > 0)->sortByDesc(fn ($x) => $x[1])->take(2)->map(fn ($x) => $x[0])->all();
        if (! $guias) {
            return ['aviso' => 'No hay una guía para eso. Explica lo que sepas en general y sugiere escribir a soporte.',
                'temas_disponibles' => collect(self::GUIAS)->map(fn ($g) => $g[0])->values()->all()];
        }
        $palabras = collect($guias)->map(fn ($g) => $g[0])->implode(' ');

        return [
            'guias' => collect($guias)->map(fn ($g) => ['titulo' => $g[0], 'pasos' => $g[2]])->values()->all(),
            'tutoriales_con_video' => Tutoriales::activos()
                ->filter(fn ($t) => collect(preg_split('/\s+/', Str::lower(Str::ascii($palabras))))->filter(fn ($p) => mb_strlen($p) > 4)
                    ->contains(fn ($p) => str_contains(Str::lower(Str::ascii($t->titulo.' '.$t->resumen)), $p)))
                ->take(3)->map(fn ($t) => ['titulo' => $t->titulo, 'video' => $t->video_url])->values()->all(),
        ];
    }
}
