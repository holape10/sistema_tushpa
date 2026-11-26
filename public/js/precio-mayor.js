/**
 * Precios automáticos de las cajas (Punto Venta y PV Móvil), en un solo lugar para que no se pisen:
 *  1. Precio del cliente: si el cliente tiene lista "Por mayor" o "Especial" (Contactos › Clientes), cada línea va con el
 *     Precio x Mayor o el Precio Especial del producto (si lo tiene).
 *  2. Precio x Mayor por cantidad: desde N unidades (Productos › "Cobrar el Precio x Mayor solo desde N"), la línea pasa
 *     al Precio x Mayor; si baja de N vuelve a su precio.
 * Solo en la unidad base (sin presentación) y sin importe fijo. Si el cajero cambia el precio a mano, se respeta.
 * El servidor ya acepta el Precio x Mayor y el Especial como precios de lista (no piden permiso).
 *
 *   PrecioMayor.aplicar(carrito, lista)   lista: 'mayor' | 'especial' | null (la del cliente de la venta)
 *
 * Cada línea trae del catálogo: precioBase, precio2 (x Mayor), precio3 (Especial) y mayor: { desde, precio }.
 * Marca la línea con autoPrecio = 'lista' | 'cantidad' (y mayorAuto / listaAuto para mostrarlo).
 */
(function () {
    const r2 = n => Math.round((Number(n) || 0) * 100) / 100;

    function objetivo(it, lista) {
        const deLista = lista === 'mayor' ? Number(it.precio2) : (lista === 'especial' ? Number(it.precio3) : 0);
        if (deLista > 0) return { precio: r2(deLista), motivo: 'lista' };
        if (it.mayor && (Number(it.cantidad) || 0) >= it.mayor.desde) return { precio: r2(it.mayor.precio), motivo: 'cantidad' };
        return null;
    }

    window.PrecioMayor = {
        aplicar(carrito, lista = null) {
            for (const it of carrito || []) {
                if (it.presentacion || (it.importe !== undefined && it.importe !== null)) continue;
                // El cajero lo cambió a mano después: se respeta y deja de ser automático
                if (it.autoPrecio && r2(it.precio) !== r2(it.autoValor)) { it.autoPrecio = null; it.autoValor = null; }
                const t = objetivo(it, lista);
                if (t && (it.autoPrecio || r2(it.precio) === r2(it.precioBase))) {
                    if (r2(it.precio) !== t.precio) it.precio = t.precio;
                    it.autoPrecio = t.motivo;
                    it.autoValor = t.precio;
                } else if (!t && it.autoPrecio) {
                    it.precio = it.precioBase;
                    it.autoPrecio = null;
                    it.autoValor = null;
                }
                const cantidad = it.autoPrecio === 'cantidad';
                const deLista = it.autoPrecio === 'lista';
                if (it.mayorAuto !== cantidad) it.mayorAuto = cantidad;
                if (it.listaAuto !== deLista) it.listaAuto = deLista;
            }
        },
    };
})();
