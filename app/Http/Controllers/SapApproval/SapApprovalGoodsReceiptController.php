<?php

namespace App\Http\Controllers\SapApproval;

class SapApprovalGoodsReceiptController extends BaseApprovalController
{
    protected function table(): string            { return 'tr_gr_header'; }
    protected function pkColumn(): string         { return 'id'; }
    protected function dateColumn(): string       { return 'gr_date'; }
    protected function approvalColumn(): string   { return 'closing_is_approved'; }
    protected function approvedByColumn(): string { return 'closing_approved_by'; }
    protected function approvedAtColumn(): string { return 'closing_approved_at'; }
    protected function routePrefix(): string      { return 'sap-approval.goods_receipt'; }
    protected function viewPrefix(): string       { return 'sap-approval.goods_receipt'; }
    protected function title(): string            { return 'Goods Receipt'; }
}