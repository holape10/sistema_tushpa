<?php

namespace App\Support\HoraLocal;

use Illuminate\Database\MySqlConnection;

/** Conexión MySQL cuyas consultas completan la hora de Perú en los inserts (ver ConsultaConHora) */
class ConexionMysql extends MySqlConnection
{
    public function query()
    {
        return new ConsultaConHora($this, $this->getQueryGrammar(), $this->getPostProcessor());
    }
}
