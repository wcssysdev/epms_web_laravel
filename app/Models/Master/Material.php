<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class Material extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_material';
    protected $fillable = ['company_id','material_code','material_name','material_uom','plant_code','sloc_code','material_batch','material_group','material_type','created_by','updated_by'];

    public function scopeSearch($q, ?string $s) {
        if (!$s) return $q;
        return $q->where(fn($x) => $x->where('material_code','ilike',"%$s%")->orWhere('material_name','ilike',"%$s%"));
    }
}
