<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class WorkCenter extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_work_center';
    protected $fillable = ['company_id','work_center_code','work_center_name','estate_code','division_code','plant_code','created_by','updated_by'];
}
