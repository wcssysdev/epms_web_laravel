<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasCompanyScope;

/**
 * Platform Checking (t_platform_checking) — auto-increment PK.
 * Detail lines stored in t_platform_checking_detail (detail_type/detail_value).
 */
class PlatformChecking extends Model
{
    use HasCompanyScope;

    protected $table  = 't_platform_checking';
    protected $fillable = [
        'company_id', 'estate_code', 'division_code', 'block_code', 'tph_code',
        'check_date', 'check_status', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'check_date' => 'date',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(PlatformCheckingDetail::class, 'platform_checking_id', 'id');
    }
}
