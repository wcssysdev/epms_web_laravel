<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log of every workplan approve/reject action.
 */
class WorkplanApprovalLog extends Model
{
    protected $table = 'log_workplan_approval';

    protected $fillable = [
        'company_id',
        'workplan_id',
        'approval_status',
        'approval_remark',
        'approved_by',
        'approved_by_name',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'approval_status' => 'integer',
    ];

    public function workplan(): BelongsTo
    {
        return $this->belongsTo(Workplan::class, 'workplan_id', 'id');
    }
}
