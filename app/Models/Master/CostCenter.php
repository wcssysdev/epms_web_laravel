<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class CostCenter extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_cost_center';
    protected $fillable = ['company_id','cc_code','cc_desc','cc_gsber','valid_from','valid_to','created_by','updated_by'];
    protected $casts    = ['valid_from' => 'date', 'valid_to' => 'date'];
}
