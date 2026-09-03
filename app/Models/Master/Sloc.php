<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class Sloc extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_sloc';
    protected $fillable = ['company_id','sloc_code','plant_code','sloc_desc','created_by','updated_by'];
}
