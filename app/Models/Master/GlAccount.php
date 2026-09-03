<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class GlAccount extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_glacc';
    protected $fillable = ['company_id','account_number','account_desc','created_by','updated_by'];
}
