<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class Wbs extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_wbs';
    protected $fillable = ['company_id','wbs_code','wbs_name','wbs_group_code','wbs_group_name','created_by','updated_by'];
}
