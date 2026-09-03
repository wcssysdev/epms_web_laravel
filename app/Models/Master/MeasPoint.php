<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class MeasPoint extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_meas_point';
    protected $fillable = [
        'company_id','plant_code','vra_order_number','equipment_code',
        'equipment_object_number','point','unit','description','created_by','updated_by',
    ];
}
