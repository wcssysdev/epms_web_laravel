<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class ReceivingPoint extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_receiving_point';
    protected $fillable = ['company_id','receiving_point_code','created_by','updated_by'];
}
