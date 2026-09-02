<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class Activity extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_activity';
    protected $fillable = ['company_id','activity_code','activity_name','activity_uom','activity_uom_name','activity_group_code','cost_by_block','cost_by_auc','cost_by_order_number','cost_by_cost_center','block_is_lc','block_is_immature','block_is_mature','block_is_scout','is_wbs_required','created_by','updated_by'];
    protected $casts    = ['cost_by_block'=>'boolean','cost_by_auc'=>'boolean','cost_by_order_number'=>'boolean','cost_by_cost_center'=>'boolean','block_is_lc'=>'boolean','block_is_immature'=>'boolean','block_is_mature'=>'boolean','block_is_scout'=>'boolean','is_wbs_required'=>'boolean'];

    public function scopeSearch($q, ?string $s) {
        if (!$s) return $q;
        return $q->where(fn($x) => $x->where('activity_code','ilike',"%$s%")->orWhere('activity_name','ilike',"%$s%"));
    }
}
