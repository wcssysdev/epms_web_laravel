<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Log an action to audit_trail table.
     *
     * @param  int     $transactionType  e.g. 1=Login, 2=Master, 3=Transaction
     * @param  int     $actionType       e.g. 1=Create, 2=Update, 3=Delete, 4=Approve
     * @param  string  $description      Human-readable description
     */
    public static function log(int $transactionType, int $actionType, string $description): void
    {
        try {
            $user = Auth::user();
            if (! $user) return;

            DB::table('audit_trail')->insert([
                'company_id'       => $user->company_id,
                'transaction_type' => $transactionType,
                'action_type'      => $actionType,
                'user_code'        => $user->username,
                'user_name'        => $user->user_name,
                'description'      => $description,
                'created_by'       => $user->username,
                'created_at'       => now(),
            ]);
        } catch (\Exception $e) {
            // Never let audit logging break the main flow
            logger()->error('AuditService failed: ' . $e->getMessage());
        }
    }

    // ── Transaction type constants ────────────────────────────────────
    const TYPE_AUTH        = 1;
    const TYPE_MASTER      = 2;
    const TYPE_TRANSACTION = 3;
    const TYPE_APPROVAL    = 4;
    const TYPE_SYSTEM      = 5;

    // ── Action type constants ─────────────────────────────────────────
    const ACTION_CREATE  = 1;
    const ACTION_UPDATE  = 2;
    const ACTION_DELETE  = 3;
    const ACTION_APPROVE = 4;
    const ACTION_REJECT  = 5;
    const ACTION_LOGIN   = 6;
    const ACTION_LOGOUT  = 7;
    const ACTION_LOCK    = 8;
    const ACTION_UNLOCK  = 9;
}
