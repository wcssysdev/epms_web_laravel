<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class SalesOrder extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_sales_order';
    protected $fillable = [
        'company_id','sales_order_no','plant_code','item_no','customer_reference','customer_code',
        'customer_desc_1','customer_desc_2','material_code','material_desc','item_qty','item_uom',
        'payment_term','inco_term_1','inco_term_2','reason_for_rejection','sales_order_type',
        'sales_order_date','item_description','sap_created_date','sap_created_by','created_by','updated_by',
    ];
}
