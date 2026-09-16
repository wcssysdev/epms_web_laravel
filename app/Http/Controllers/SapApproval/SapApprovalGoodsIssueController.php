<?php

namespace App\Http\Controllers\SapApproval;

class SapApprovalGoodsIssueController extends BaseApprovalController
{
    protected function table(): string            { return 'tr_gi_header'; }
    protected function pkColumn(): string         { return 'id'; }
    protected function dateColumn(): string       { return 'gi_date'; }
    protected function approvalColumn(): string   { return 'closing_is_approved'; }
    protected function approvedByColumn(): string { return 'closing_approved_by'; }
    protected function approvedAtColumn(): string { return 'closing_approved_at'; }
    protected function routePrefix(): string      { return 'sap-approval.goods_issue'; }
    protected function viewPrefix(): string       { return 'sap-approval.goods_issue'; }
    protected function title(): string            { return 'Goods Issue'; }
}