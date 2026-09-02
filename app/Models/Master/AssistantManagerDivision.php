<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class AssistantManagerDivision extends Model
{
    use HasCompanyScope;

    protected $table    = 'm_assistant_manager_division';
    protected $fillable = [
        'company_id',
        'assistant_manager_code',
        'assistant_manager_name',
        'division_code',
        'division_name',
        'created_by',
        'updated_by',
    ];

    public function scopeByManager($q, string $code)
    {
        return $q->where('assistant_manager_code', $code);
    }

    public function scopeByDivision($q, string $code)
    {
        return $q->where('division_code', $code);
    }
}
