<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class FieldStaffGang extends Model
{
    use HasCompanyScope;

    protected $table    = 'm_field_staff_gang';
    protected $fillable = [
        'company_id',
        'field_staff_gang_code',
        'field_staff_employee_code',
        'field_staff_employee_name',
        'created_by',
        'updated_by',
    ];

    public function scopeByGang($q, string $gangCode)
    {
        return $q->where('field_staff_gang_code', $gangCode);
    }
}
