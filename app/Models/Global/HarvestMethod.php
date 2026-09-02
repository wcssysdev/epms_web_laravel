<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class HarvestMethod extends Model
{
    protected $table    = 'm_harvest_method';
    protected $primaryKey = 'mhm_id';
    protected $fillable = ['mhm_indicator','mhm_abbreviation','mhm_description','mhm_order_number_flag','created_by','updated_by'];
}
