/**
 * Precio x Mayor automático (Productos › "Cobrar el Precio x Mayor solo desde N unidades"), compartido por las cajas.
 * Cada línea del carrito trae `mayor: { desde, precio }` del catálogo. Al llegar a esa cantidad (en la unidad base, sin
 * presentación) la línea pasa al Precio x Mayor; si baja de esa cantidad vuelve a su precio. Si el cajero puso otro
 * precio a mano, se respeta. El servidor ya acepta el Precio x Mayor como precio de lista (no pide permiso).
 *
 *   PrecioMayor.aplicar(carrito)   después de cualquier cambio de cantidad (las cajas lo llaman con $watch)
 */
(function () {
    const r2 = n => Math.round((Number(n) || 0) * 100) / 100;

    window.PrecioMayor = {
        aplicar(carrito) {
            for (const it of carrito || []) {
                if (!it.mayor || it.presentacion || (it.importe !== undefined && it.importe !== null)) continue;
                const llega = (Number(it.cantidad) || 0) >= it.mayor.desde;
                if (llega && !it.mayorAuto && r2(it.precio) === r2(it.precioBase)) {
                    it.precio = it.mayor.precio;
                    it.mayorAuto = true;
                } else if (!llega && it.mayorAuto) {
                    // Vuelve a su precio solo si nadie lo cambió a mano mientras tanto
                    if (r2(it.precio) === r2(it.mayor.precio)) it.precio = it.precioBase;
                    it.mayorAuto = false;
                }
            }
        },
    };
})();
