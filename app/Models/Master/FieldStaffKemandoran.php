<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class FieldStaffKemandoran extends Model
{
    use HasCompanyScope;

    protected $table    = 'm_field_staff_kemandoran';
    protected $fillable = [
        'company_id',
        'field_staff_employee_code',
        'field_staff_employee_name',
        'mandor_employee_code',
        'mandor_employee_name',
        'created_by',
        'updated_by',
    ];

    public function scopeByMandor($q, string $code)
    {
        return $q->where('mandor_employee_code', $code);
    }
}
