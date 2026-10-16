<?php

// app/Models/Producto.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';

    protected $primaryKey = 'IdProducto';

    protected $guarded = [];

    /** Entrada del menú (SOPA, TEQUEÑOS...): no se vende sola, el mozo la elige en un plato que "lleva entrada" */
    public const OPCION = 8;

    /** Tipos que no aparecen en las pantallas de venta: insumos y opciones */
    public const NO_VENDIBLES = [4, self::OPCION];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'cat_id', 'cat_id');
    }

    public function subcategoria()
    {
        return $this->belongsTo(Subcategoria::class, 'subcat_id', 'subcat_id');
    }

    public function itemsCombo()
    {
        return $this->hasMany(Combo::class, 'IdProducto_rel', 'IdProducto');
    }

    public function presentaciones()
    {
        return $this->hasMany(ProductoPresentacion::class, 'IdProducto', 'IdProducto')->where('estado', 1)->orderBy('factor');
    }

    public function preciosDinamicos()
    {
        return $this->hasMany(ProductoPrecioDinamico::class, 'IdProducto', 'IdProducto')->orderBy('dia')->orderBy('hora_inicio');
    }

    /** URL pública de la imagen (null si no tiene) */
    public function getImagenUrlAttribute(): ?string
    {
        return $this->imagenproducto ? asset($this->imagenproducto) : null;
    }

    /** Hasta 4 fotos por producto: la principal (imagenproducto) y 3 más */
    public const MAX_IMAGENES = 4;

    /** @return array<int, string> Rutas de las fotos adicionales (sin la principal) */
    public function getFotosExtraAttribute(): array
    {
        return self::fotosExtra($this->imagenes_extra);
    }

    /** @return array<int, string> Lee la columna imagenes_extra (JSON) de un producto o de una fila de consulta */
    public static function fotosExtra(?string $json): array
    {
        return array_values(array_filter((array) json_decode((string) $json, true), fn ($r) => is_string($r) && $r !== ''));
    }

    public function getTipoNombreAttribute(): string
    {
        return match ((int) $this->promocion) {
            4 => 'Insumo',
            2 => 'Preparado',
            6 => 'Combo',
            self::OPCION => 'Entrada',
            default => 'Producto',
        };
    }
}
