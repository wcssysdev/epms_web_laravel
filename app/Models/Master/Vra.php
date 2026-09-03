<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class Vra extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_vra';
    protected $fillable = [
        'company_id','license_number','equipment_code','object_type','plant_code',
        'vra_order_number','valid_from','valid_to','created_by','updated_by',
    ];
    protected $casts = ['valid_from' => 'date', 'valid_to' => 'date'];
}
