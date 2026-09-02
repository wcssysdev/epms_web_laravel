<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class Uom extends Model
{
    protected $table    = 'm_uom';
    protected $fillable = ['uom_code', 'uom_desc', 'created_by', 'updated_by'];
}
