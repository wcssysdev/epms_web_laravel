<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class Worktype extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_worktype';
    protected $fillable = ['company_id','worktype_code','worktype_name','created_by','updated_by'];
}
