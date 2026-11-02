{{-- JS: promociones automáticas (2x1, 3x2, "3 por S/ 40"). La misma cuenta que PromocionesVenta::aplicar en el servidor (va dentro de cobrar.blade.php) --}}
    // ---------- Promociones automáticas ----------
    /** items: [{ id, cantidad, precio, base }] sin las cortesías. Devuelve lo que se ahorra y en qué promos */
    function calcularPromos(items) {
        const usadas = items.map(() => 0);
        const aplicadas = [];
        let total = 0;
        PROMOS.forEach(p => {
            const lleva = Math.max(1, p.lleva);
            let unidades = [];
            items.forEach((it, i) => {
                if (!it.base || !p.productos.includes(Number(it.id))) return;
                const libres = Math.floor(it.cantidad + 0.0001) - usadas[i];
                for (let u = 0; u < libres; u++) unidades.push({ linea: i, precio: it.precio });
            });
            const grupos = Math.floor(unidades.length / lleva);
            if (!grupos) return;
            unidades.sort((a, b) => b.precio - a.precio);
            unidades = unidades.slice(0, grupos * lleva);
            let ahorro = 0, veces = 0;
            for (let g = 0; g < grupos; g++) {
                const grupo = unidades.slice(g * lleva, (g + 1) * lleva);
                let partes;
                if (p.tipo === 'LLEVA_PAGA') {
                    const paga = Math.max(0, Math.min(grupo.length, p.paga));
                    partes = grupo.map((u, k) => k >= paga ? u.precio : 0);
                } else {
                    const suma = grupo.reduce((a, u) => a + u.precio, 0);
                    const desc = Math.round((suma - p.precio) * 100) / 100;
                    partes = desc > 0 && suma > 0 ? grupo.map(u => Math.round(desc * u.precio / suma * 100) / 100) : grupo.map(() => 0);
                    if (desc > 0) partes[0] = Math.round((partes[0] + desc - partes.reduce((a, x) => a + x, 0)) * 100) / 100;
                }
                const suma = partes.reduce((a, x) => a + x, 0);
                if (suma <= 0) continue;
                veces++;
                grupo.forEach(u => usadas[u.linea]++);
                ahorro += suma;
            }
            if (veces) {
                ahorro = Math.round(ahorro * 100) / 100;
                aplicadas.push({ nombre: p.nombre, veces, ahorro });
                total += ahorro;
            }
        });
        return { total: Math.round(total * 100) / 100, aplicadas };
    }

    /** Resta las promociones a lo que se cobra (BRUTO) */
    function aplicarPromos(items) {
        if (!PROMOS.length) return;
        const r = calcularPromos(items);
        PROMOS_APLICADAS = r.aplicadas;
        BRUTO = Math.max(0, Math.round((BRUTO - r.total) * 100) / 100);
    }

    // La cuenta de la mesa ya trae la promo al abrir la pantalla
    if (PROMOS.length && !DIRECTA) recalcular();
