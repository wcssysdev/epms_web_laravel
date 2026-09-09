<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;

class PlatformCheckingDetail extends Model
{
    protected $table    = 't_platform_checking_detail';
    public $timestamps  = false;

    protected $fillable = [
        'company_id', 'platform_checking_id', 'detail_type', 'detail_value',
    ];
}
