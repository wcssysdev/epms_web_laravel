<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class Destination extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_destination';
    protected $fillable = ['company_id','destination_code','destination_name','created_by','updated_by'];
}
