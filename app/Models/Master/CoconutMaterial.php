<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class CoconutMaterial extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_coconut_material';
    protected $fillable = ['company_id','material_code','material_desc','material_uom','plant_code','created_by','updated_by'];
}
