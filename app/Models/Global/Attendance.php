<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table    = 'm_attendance';
    protected $fillable = ['attendance_code', 'attendance_desc', 'created_by', 'updated_by'];

    public function scopeSearch($q, ?string $s)
    {
        if (!$s) return $q;
        return $q->where(fn($x) =>
            $x->where('attendance_code', 'ilike', "%$s%")
              ->orWhere('attendance_desc', 'ilike', "%$s%")
        );
    }
}
