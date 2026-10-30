<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** Una cuenta bancaria o un QR de cobro de un usuario del panel (cada uno puede tener varios) */
class SuperadminCobro extends Model
{
    protected $connection = 'central';

    protected $table = 'superadmin_cobros';

    protected $guarded = [];
}
