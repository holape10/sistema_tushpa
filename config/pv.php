<?php

// PV táctil
return [
    // Desde este total se pide el nombre del cliente / N° de beeper (0 = nunca obligatorio)
    'nombre_obligatorio_desde' => (float) env('PV_NOMBRE_OBLIGATORIO_DESDE', 20),
];
