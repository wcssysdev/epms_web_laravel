<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class Device extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_devices';
    protected $fillable = ['company_id','device_code','estate_code','device_imei','created_by','updated_by'];
}
