<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class GangEmployee extends Model
{
    use HasCompanyScope;

    protected $table    = 'm_gang_employee';
    protected $fillable = [
        'company_id',
        'gang_code',
        'gang_employee_code',
        'gang_employee_name',
        'created_by',
        'updated_by',
    ];

    public function scopeByGang($q, string $gangCode)
    {
        return $q->where('gang_code', $gangCode);
    }

    public function scopeSearch($q, ?string $s)
    {
        if (!$s) return $q;
        return $q->where(fn($x) =>
            $x->where('gang_employee_code', 'ilike', "%$s%")
              ->orWhere('gang_employee_name', 'ilike', "%$s%")
              ->orWhere('gang_code', 'ilike', "%$s%")
        );
    }
}
