<?php

namespace Tests\Unit;

use App\Support\Antiguo\CargadorSql;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** El respaldo del sistema antiguo que sube una empresa no puede tocar otras bases ni la central */
class CargadorSqlSeguroTest extends TestCase
{
    /** @return array<string, array{string}> lo que traen los respaldos de verdad (mysqldump, phpMyAdmin, HeidiSQL) */
    public static function legitimos(): array
    {
        return [
            'insert con decimales y textos con punto y coma' => ["INSERT INTO `productos` VALUES (1,'LOMO; SALTADO',25.50,'S.A.C.','D\\'Agro'),(2,'CAFÉ \"ESPECIAL\"',-3.5,NULL,'1.2.3');"],
            'insert con columnas' => ["INSERT INTO productos (IdProducto, pronom, propun) VALUES (5, 'INCA KOLA 1.5L', 7.00);"],
            'phpmyadmin con comillas dobladas' => ["INSERT INTO `cliente` VALUES (1,'O''BRIEN EIRL','20100070970');"],
            'create table' => ["CREATE TABLE `productos` (\n `IdProducto` int NOT NULL AUTO_INCREMENT,\n `propun` decimal(10,2) DEFAULT '0.00',\n PRIMARY KEY (`IdProducto`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='lista; de precios';"],
            'drop de una tabla' => ['DROP TABLE IF EXISTS `productos`;'],
            'alter de llaves' => ['ALTER TABLE `productos` ADD PRIMARY KEY (`IdProducto`), MODIFY `IdProducto` int NOT NULL AUTO_INCREMENT;'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function ataques(): array
    {
        return [
            'borrar la central con un drop de varias tablas' => ['DROP TABLE `productos`, bd_tushpa_central.clientes;'],
            'copiar datos de otra empresa' => ['CREATE TABLE productos SELECT * FROM bd_20100070970.users;'],
            'insert desde otra base' => ['INSERT INTO productos SELECT * FROM `bd_tushpa_central`.`superadmins`;'],
            'dos sentencias en una' => ["INSERT INTO productos VALUES (1,'a'); DROP DATABASE bd_tushpa_central;"],
            'sentencia escondida en un comentario' => ['INSERT INTO productos VALUES (1) /*!50000 ; DROP DATABASE x */;'],
            'mover una tabla a otra base' => ['ALTER TABLE productos RENAME TO bd_otra.productos;'],
            'leer archivos del servidor' => ["INSERT INTO productos VALUES (LOAD_FILE('/etc/passwd'));"],
            'copiar estructura de otra base' => ['CREATE TABLE productos LIKE bd_tushpa_central.clientes;'],
        ];
    }

    #[DataProvider('legitimos')]
    public function test_un_respaldo_normal_se_carga(string $sql): void
    {
        $this->assertTrue(CargadorSql::seguro($sql));
    }

    public function test_la_tabla_se_crea_sin_las_llaves_foraneas_que_mysql_8_4_rechaza(): void
    {
        $sql = "CREATE TABLE `compras_cabecera` (\n  `com_cab_id` int NOT NULL,\n  `id_almacen` int DEFAULT NULL,\n  PRIMARY KEY (`com_cab_id`),\n"
            ."  KEY `compras_sucursal` (`id_almacen`),\n  CONSTRAINT `compras_sucursal` FOREIGN KEY (`id_almacen`) REFERENCES `almacenes` (`id_almacen`)\n) ENGINE=InnoDB";

        $this->assertSame("CREATE TABLE `compras_cabecera` (\n  `com_cab_id` int NOT NULL,\n  `id_almacen` int DEFAULT NULL,\n  PRIMARY KEY (`com_cab_id`),\n"
            ."  KEY `compras_sucursal` (`id_almacen`)\n) ENGINE=InnoDB", CargadorSql::sinLlavesForaneas($sql));
    }

    #[DataProvider('ataques')]
    public function test_un_respaldo_armado_para_atacar_no_se_ejecuta(string $sql): void
    {
        $this->assertFalse(CargadorSql::seguro($sql));
    }
}
