<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class Vendor extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_vendor';
    protected $fillable = ['company_id','vendor_code','vendor_name','plant_code','created_by','updated_by'];
}
