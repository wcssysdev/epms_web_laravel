<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class GlaOrder extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_glacc_gi_order';
    protected $fillable = ['company_id','account_number','account_desc','created_by','updated_by'];
}
