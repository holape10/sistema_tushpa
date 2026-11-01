<?php

use Illuminate\Support\Facades\Broadcast;

// Tiempo real: cada sucursal tiene su canal privado (sucursal.{RUC}.{sucursal}); solo lo escuchan sus usuarios
Broadcast::channel('sucursal.{ruc}.{sucursal}', function ($user, string $ruc, string $sucursal) {
    return (string) $user->IdEmpresa === $ruc && (int) $user->id_empresa_negocio === (int) $sucursal;
});
